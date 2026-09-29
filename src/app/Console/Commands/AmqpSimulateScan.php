<?php

namespace App\Console\Commands;

use App\Models\Turnstile;
use App\Models\User;
use App\Services\Amqp\AmqpConnection;
use App\Services\Amqp\AmqpPublisher;
use App\Services\Amqp\Topology;
use App\Services\Qr\QrTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel;

class AmqpSimulateScan extends Command
{
    protected $signature = 'amqp:simulate-scan
        {turnstile : ID del torniquete}
        {email? : Usuario que "presenta" el QR (se emite un token nuevo)}
        {--token= : Enviar este token en lugar de emitir uno}
        {--repeat=1 : Cuántas veces enviar el MISMO token (prueba de replay)}
        {--wait=5 : Segundos a esperar la decisión}';

    protected $description = 'Simula un torniquete: publica un escaneo y espera la decisión';

    public function handle(AmqpConnection $amqp, QrTokenService $qr): int
    {
        $turnstile = Turnstile::find((int) $this->argument('turnstile'));
        if (! $turnstile) {
            $this->error('El torniquete no existe.');

            return self::FAILURE;
        }

        $token = $this->option('token');
        if (! $token) {
            $user = User::where('email', $this->argument('email'))->first();
            if (! $user) {
                $this->error('Indica un email válido o usa --token=');

                return self::FAILURE;
            }
            $token = $qr->issue($user)['token'];
        }

        $exit = self::SUCCESS;

        try {
            $publisher = new AmqpPublisher($amqp);
            $channel = $amqp->channel();

            for ($i = 1, $n = max(1, (int) $this->option('repeat')); $i <= $n; $i++) {
                $correlationId = (string) Str::uuid();

                $publisher->publish(
                    Topology::EVENTS_EXCHANGE,
                    Topology::scanRoutingKey($turnstile->building_id, $turnstile->id),
                    [
                        'turnstile_id' => $turnstile->id,
                        'qr_token' => $token,
                        'scanned_at' => now()->toIso8601String(),
                    ],
                    $correlationId,
                    'access.scan',
                );

                $decision = $this->awaitDecision(
                    $channel,
                    Topology::turnstileQueue($turnstile->id),
                    $correlationId,
                    (float) $this->option('wait'),
                );

                if ($decision === null) {
                    $this->warn("#{$i} Sin decisión a tiempo: {$turnstile->code} se queda CERRADO (fail-closed).");
                    $exit = self::FAILURE;
                } elseif ($decision['granted']) {
                    $this->info("#{$i} ABRE · {$turnstile->code} · motivo: {$decision['reason']}");
                } else {
                    $this->error("#{$i} NIEGA · {$turnstile->code} · motivo: {$decision['reason']}");
                }
            }
        } finally {
            $amqp->close();
        }

        return $exit;
    }

    /** Lee la cola del torniquete hasta encontrar la decisión de este escaneo. */
    private function awaitDecision(AMQPChannel $channel, string $queue, string $correlationId, float $wait): ?array
    {
        $deadline = microtime(true) + $wait;

        while (microtime(true) < $deadline) {
            $message = $channel->basic_get($queue);

            if ($message === null) {
                usleep(100_000);

                continue;
            }

            $message->ack();

            // Decisiones de otros escaneos (o viejas): se descartan
            if (! $message->has('correlation_id') || $message->get('correlation_id') !== $correlationId) {
                continue;
            }

            return json_decode($message->getBody(), true);
        }

        return null;
    }
}
