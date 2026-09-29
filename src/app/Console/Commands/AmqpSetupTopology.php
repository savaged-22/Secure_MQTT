<?php

namespace App\Console\Commands;

use App\Models\Turnstile;
use App\Services\Amqp\AmqpConnection;
use App\Services\Amqp\Topology;
use Illuminate\Console\Command;

class AmqpSetupTopology extends Command
{
    protected $signature = 'amqp:setup-topology';

    protected $description = 'Declara exchanges, colas y bindings en RabbitMQ (idempotente)';

    public function handle(AmqpConnection $amqp): int
    {
        $turnstiles = Turnstile::orderBy('id')->get(['id', 'building_id', 'code']);

        if ($turnstiles->isEmpty()) {
            $this->warn('No hay torniquetes en la base de datos. Ejecuta primero: php artisan db:seed');
        }

        try {
            $rows = (new Topology())->declare($amqp->channel(), $turnstiles);
        } catch (\Throwable $e) {
            $this->error('No se pudo declarar la topología: ' . $e->getMessage());
            $this->line('Verifica que RabbitMQ esté arriba y que las variables RABBITMQ_* sean correctas.');

            return self::FAILURE;
        } finally {
            $amqp->close();
        }

        $this->table(['Tipo', 'Nombre', 'Detalle'], $rows);
        $this->info('Topología lista (' . $turnstiles->count() . ' colas de torniquete).');

        return self::SUCCESS;
    }
}
