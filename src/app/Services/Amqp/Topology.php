<?php

namespace App\Services\Amqp;

use Illuminate\Support\Collection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

class Topology
{
    public const EVENTS_EXCHANGE = 'access.events';
    public const DECISIONS_EXCHANGE = 'access.decisions';
    public const DLX_EXCHANGE = 'access.dlx';

    public const VALIDATION_QUEUE = 'access.validation';
    public const AUDIT_QUEUE = 'access.audit';
    public const DLQ = 'access.dlq';

    /** Una decisión vieja nunca debe abrir un torniquete: caduca a los 30 s. */
    public const DECISION_TTL_MS = 30000;

    public static function scanRoutingKey(int $buildingId, int $turnstileId): string
    {
        return "building.{$buildingId}.turnstile.{$turnstileId}.scan";
    }

    public static function decisionRoutingKey(int $turnstileId): string
    {
        return "turnstile.{$turnstileId}.decision";
    }

    public static function turnstileQueue(int $turnstileId): string
    {
        return "turnstile.{$turnstileId}.commands";
    }

    /**
     * Declara toda la topología. Es idempotente: se puede ejecutar varias veces.
     *
     * @param  Collection<int, \App\Models\Turnstile>  $turnstiles
     * @return array<int, array{0: string, 1: string, 2: string}>  filas para mostrar en consola
     */
    public function declare(AMQPChannel $channel, Collection $turnstiles): array
    {
        $rows = [];

        $queue = function (string $name, array $args = [], string $detail = 'durable') use ($channel, &$rows) {
            $channel->queue_declare($name, false, true, false, false, false, $args ? new AMQPTable($args) : []);
            $rows[] = ['queue', $name, $detail];
        };

        $bind = function (string $queueName, string $exchange, string $key) use ($channel, &$rows) {
            $channel->queue_bind($queueName, $exchange, $key);
            $rows[] = ['binding', "{$exchange} -> {$queueName}", $key];
        };

        // Exchanges
        foreach ([self::EVENTS_EXCHANGE, self::DECISIONS_EXCHANGE, self::DLX_EXCHANGE] as $exchange) {
            $channel->exchange_declare($exchange, 'topic', false, true, false);
            $rows[] = ['exchange', $exchange, 'topic, durable'];
        }

        // Dead Letter Queue
        $queue(self::DLQ);
        $bind(self::DLQ, self::DLX_EXCHANGE, '#');

        // Validación: consume los escaneos de QR
        $queue(self::VALIDATION_QUEUE, [
            'x-dead-letter-exchange' => self::DLX_EXCHANGE,
            'x-dead-letter-routing-key' => 'validation',
        ], 'durable, con DLX');
        $bind(self::VALIDATION_QUEUE, self::EVENTS_EXCHANGE, 'building.*.turnstile.*.scan');

        // Auditoría: recibe copia de todo
        $queue(self::AUDIT_QUEUE, [
            'x-dead-letter-exchange' => self::DLX_EXCHANGE,
            'x-dead-letter-routing-key' => 'audit',
        ], 'durable, con DLX');
        $bind(self::AUDIT_QUEUE, self::EVENTS_EXCHANGE, '#');
        $bind(self::AUDIT_QUEUE, self::DECISIONS_EXCHANGE, '#');

        // Una cola por torniquete para recibir su decisión
        foreach ($turnstiles as $turnstile) {
            $name = self::turnstileQueue($turnstile->id);

            $queue($name, [
                'x-message-ttl' => self::DECISION_TTL_MS,
            ], 'durable, TTL 30 s');
            $bind($name, self::DECISIONS_EXCHANGE, self::decisionRoutingKey($turnstile->id));
        }

        return $rows;
    }
}
