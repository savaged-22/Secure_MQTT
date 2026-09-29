<?php

namespace App\Services\Amqp;

use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

/**
 * Publica mensajes JSON persistentes, con confirmación del broker
 * y con "mandatory": si nadie recibe el mensaje, lanza una excepción.
 */
class AmqpPublisher
{
    private AMQPChannel $channel;

    public function __construct(AmqpConnection $amqp)
    {
        $this->channel = $amqp->openChannel();
        $this->channel->confirm_select();
        $this->channel->set_return_listener(function ($code, $text, $exchange, $routingKey) {
            throw new RuntimeException(
                "Mensaje no enrutable ({$code} {$text}): exchange={$exchange}, routing_key={$routingKey}"
            );
        });
    }

    public function publish(
        string $exchange,
        string $routingKey,
        array $payload,
        string $correlationId,
        string $type,
    ): void {
        $message = new AMQPMessage(json_encode($payload, JSON_THROW_ON_ERROR), [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'correlation_id' => $correlationId,
            'message_id' => (string) Str::uuid(),
            'timestamp' => time(),
            'type' => $type,
        ]);

        $this->channel->basic_publish($message, $exchange, $routingKey, true);
        $this->channel->wait_for_pending_acks_returns(5);
    }
}
