<?php

namespace App\Services\Amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class AmqpConnection
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function connection(): AMQPStreamConnection
    {
        if ($this->connection === null || ! $this->connection->isConnected()) {
            $c = config('amqp');

            $this->connection = new AMQPStreamConnection(
                $c['host'],
                $c['port'],
                $c['user'],
                $c['password'],
                $c['vhost'],
                connection_timeout: $c['connection_timeout'],
                read_write_timeout: $c['read_write_timeout'],
                keepalive: true,
                heartbeat: $c['heartbeat'],
            );
            $this->channel = null;
        }

        return $this->connection;
    }

    /** Canal compartido (el mismo en cada llamada). */
    public function channel(): AMQPChannel
    {
        $connection = $this->connection();

        if ($this->channel === null || ! $this->channel->is_open()) {
            $this->channel = $connection->channel();
        }

        return $this->channel;
    }

    /** Canal nuevo e independiente (por ejemplo, para publicar con confirmaciones). */
    public function openChannel(): AMQPChannel
    {
        return $this->connection()->channel();
    }

    public function close(): void
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            $this->channel->close();
        }

        if ($this->connection !== null && $this->connection->isConnected()) {
            $this->connection->close();
        }

        $this->channel = null;
        $this->connection = null;
    }
}
