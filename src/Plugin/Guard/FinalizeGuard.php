<?php
/**
 * This file is part of the prooph/service-bus.
 * (c) 2013-2017 prooph software GmbH <contact@prooph.de>
 * (c) 2015-2017 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Prooph\ServiceBus\Plugin\Guard;

use Prooph\Common\Event\ActionEvent;
use Prooph\Common\Event\ActionEventEmitter;
use Prooph\Common\Event\ActionEventListenerAggregate;
use Prooph\Common\Event\DetachAggregateHandlers;
use Prooph\ServiceBus\MessageBus;
use Prooph\ServiceBus\QueryBus;
use React\Promise\Promise;

/**
 * Class FinalizeGuard
 * @package Prooph\ServiceBus\Plugin\Guard
 */
final class FinalizeGuard implements ActionEventListenerAggregate
{
    use DetachAggregateHandlers;

    /**
     * @var AuthorizationService
     */
    private $authorizationService;

    /**
     * @var bool
     */
    private $exposeEventMessageName;

    /**
     * @param AuthorizationService $authorizationService
     * @param bool $exposeEventMessageName
     */
    public function __construct(AuthorizationService $authorizationService, $exposeEventMessageName = false)
    {
        $this->authorizationService = $authorizationService;
        $this->exposeEventMessageName = $exposeEventMessageName;
    }

    /**
     * @param ActionEvent $actionEvent
     * @throws UnauthorizedException
     */
    public function onFinalize(ActionEvent $actionEvent)
    {
        $promise = $actionEvent->getParam(QueryBus::EVENT_PARAM_PROMISE);
        $messageName = $actionEvent->getParam(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if ($promise instanceof Promise) {
            $newPromise = $promise->then(function ($result) use ($actionEvent, $messageName) {
                if (!$this->authorizationService->isGranted($messageName, $result)) {
                    $actionEvent->stopPropagation(true);

                    if (! $this->exposeEventMessageName) {
                        $messageName = '';
                    }

                    throw new UnauthorizedException($messageName);
                }
            });

            $actionEvent->setParam(QueryBus::EVENT_PARAM_PROMISE, $newPromise);
        } elseif (!$this->authorizationService->isGranted($messageName)) {
            $actionEvent->stopPropagation(true);

            if (! $this->exposeEventMessageName) {
                $messageName = '';
            }

            throw new UnauthorizedException($messageName);
        }
    }

    /**
     * @param ActionEventEmitter $events
     *
     * @return void
     */
    public function attach(ActionEventEmitter $events)
    {
        $this->trackHandler($events->attachListener(MessageBus::EVENT_FINALIZE, [$this, "onFinalize"], -1000));
    }
}
