<?php

namespace App\Console\Commands;

use App\Models\Turnstile;
use App\Models\User;
use App\Services\Access\AccessDecision;
use App\Services\Access\AccessPolicyService;
use App\Services\Amqp\AmqpConnection;
use App\Services\Amqp\AmqpPublisher;
use App\Services\Amqp\Topology;
use App\Services\Qr\QrTokenService;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

class AmqpValidateAccess extends Command
{
    protected $signature = 'amqp:validate-access';

    protected $description = 'Worker: valida los escaneos de QR y publica la decisión de acceso';

    private bool $shouldStop = false;

    public function handle(AmqpConnection $amqp, QrTokenService $qr, AccessPolicyService $policy): int
    {
        pcntl_async_signals(true);
        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, function () {
                $this->shouldStop = true;
                $this->line('Cerrando después del mensaje en curso...');
            });
        }

        $publisher = new AmqpPublisher($amqp);

        $channel = $amqp->channel();
        $channel->basic_qos(null, 1, null);   // un mensaje a la vez
        $channel->basic_consume(
            Topology::VALIDATION_QUEUE,
            '',
            false,   // no_local
            false,   // no_ack = false -> ACK manual
            false,   // exclusive
            false,   // nowait
            fn (AMQPMessage $message) => $this->process($message, $qr, $policy, $publisher),
        );

        $this->info('Escuchando en ' . Topology::VALIDATION_QUEUE . ' (Ctrl+C para salir)');

        while (! $this->shouldStop && $channel->is_consuming()) {
            try {
                $channel->wait(null, false, 5);
            } catch (AMQPTimeoutException) {
                // Sin mensajes: solo revisamos la bandera de parada
            }
        }

        $amqp->close();
        $this->info('Worker detenido.');

        return self::SUCCESS;
    }

    private function process(
        AMQPMessage $message,
        QrTokenService $qr,
        AccessPolicyService $policy,
        AmqpPublisher $publisher,
    ): void {
        try {
            $this->handleScan($message, $qr, $policy, $publisher);
        } catch (\Throwable $e) {
            // Sin requeue: el QR ya pudo quedar consumido, y reintentar solo daría "replayed".
            // El mensaje va a la DLQ y el estudiante puede volver a escanear con un QR nuevo.
            $this->error('Error procesando el mensaje: ' . $e->getMessage());
            report($e);
            $message->nack(false);
        }
    }

    private function handleScan(
        AMQPMessage $message,
        QrTokenService $qr,
        AccessPolicyService $policy,
        AmqpPublisher $publisher,
    ): void {
        if (! $message->has('correlation_id')) {
            $this->reject($message, 'sin correlation_id');

            return;
        }
        $correlationId = $message->get('correlation_id');

        try {
            $scan = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->reject($message, 'JSON inválido');

            return;
        }

        if (! is_array($scan)
            || ! is_int($scan['turnstile_id'] ?? null)
            || ! is_string($scan['qr_token'] ?? null)) {
            $this->reject($message, 'payload inválido');

            return;
        }

        $turnstile = Turnstile::find($scan['turnstile_id']);
        if (! $turnstile) {
            $this->reject($message, "torniquete {$scan['turnstile_id']} inexistente");

            return;
        }

        // La routing key debe coincidir con el torniquete declarado en el payload
        $expectedKey = Topology::scanRoutingKey($turnstile->building_id, $turnstile->id);
        if ($message->getRoutingKey() !== $expectedKey) {
            $this->reject($message, "routing key {$message->getRoutingKey()} no coincide con {$expectedKey}");

            return;
        }

        // Decisión
        $user = null;
        $validation = $qr->validate($scan['qr_token']);

        if ($validation->ok) {
            $user = User::find($validation->userId);
            // Se usa la hora del servidor, no la que declare el dispositivo
            $decision = $user
                ? $policy->evaluate($user, $turnstile, now())
                : AccessDecision::deny('user_not_found');
        } else {
            $decision = AccessDecision::deny('qr_' . $validation->reason);
        }

        // Publicar la decisión (con confirmación del broker) ANTES de hacer ACK
        $publisher->publish(
            Topology::DECISIONS_EXCHANGE,
            Topology::decisionRoutingKey($turnstile->id),
            [
                'correlation_id' => $correlationId,
                'turnstile_id' => $turnstile->id,
                'user_id' => $user?->id,
                'granted' => $decision->granted,
                'reason' => $decision->reason,
                'scanned_at' => $scan['scanned_at'] ?? null,
                'decided_at' => now()->toIso8601String(),
            ],
            $correlationId,
            'access.decision',
        );

        $this->line(sprintf(
            '[%s] %s · %s · %s (%s)',
            now(config('access.timezone'))->format('H:i:s'),
            $turnstile->code,
            $user?->name ?? '—',
            $decision->granted ? 'ABRE' : 'NIEGA',
            $decision->reason,
        ));

        $message->ack();   // siempre lo último
    }

    private function reject(AMQPMessage $message, string $why): void
    {
        $this->warn("Mensaje rechazado -> DLQ: {$why}");
        $message->nack(false);
    }
}
