<?php
/**
 * This file is part of the LdapTools package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LdapTools\Operation\Invoker;

use LdapTools\Exception\LdapConnectionException;
use LdapTools\Log\LogOperation;
use LdapTools\Operation\AuthenticationOperation;
use LdapTools\Operation\Handler\AuthenticationOperationHandler;
use LdapTools\Operation\Handler\OperationHandler;
use LdapTools\Operation\Handler\QueryOperationHandler;
use LdapTools\Operation\LdapOperationInterface;

/**
 * Invokes the correct handler for a given operation, as well as handling logging.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class LdapOperationInvoker implements LdapOperationInvokerInterface
{
    use LdapOperationInvokerTrait;

    public function __construct()
    {
        $this->addHandler(new OperationHandler());
        $this->addHandler(new QueryOperationHandler());
        $this->addHandler(new AuthenticationOperationHandler());
    }

    /**
     * {@inheritdoc}
     */
    public function execute(LdapOperationInterface $operation)
    {
        $log = $this->getLogObject($operation);
        $state = new ConnectionState($this->connection);

        try {
            $handler = $this->getOperationHandler($operation);
            $handler->setOperationDefaults($operation);
            $this->logStart($log);
            $this->switchServerIfNeeded($this->connection->getServer(), $operation->getServer(), $operation);

            return $handler->execute($operation);
        } catch (\Throwable $e) {
            $this->logExceptionAndThrow($e, $log);
        } catch (\Exception $e) {
            $this->logExceptionAndThrow($e, $log);
        } finally {
            $this->logEnd($log);
            $this->switchServerIfNeeded($this->connection->getServer(), $state->getLastServer(), $operation);
        }
    }

    /**
     * Construct the LogOperation object for the operation.
     *
     * @param LdapOperationInterface $operation
     * @return LogOperation|null
     */
    protected function getLogObject(LdapOperationInterface $operation)
    {
        if (!$this->logger) {
            return null;
        }

        return (new LogOperation($operation))->setDomain($this->connection->getConfig()->getDomainName());
    }

    /**
     * Find and return a supported handler for the operation.
     *
     * @param LdapOperationInterface $operation
     * @return \LdapTools\Operation\Handler\OperationHandlerInterface
     * @throws LdapConnectionException
     */
    protected function getOperationHandler(LdapOperationInterface $operation)
    {
        foreach ($this->handler as $handler) {
            if ($handler->supports($operation)) {
                $handler->setConnection($this->connection);
                $handler->setEventDispatcher($this->dispatcher);

                return $handler;
            }
        }

        throw new LdapConnectionException(sprintf(
            'Operation "%s" with a class name "%s" does not have a supported operation handler.',
            $operation->getName(),
            get_class($operation)
        ));
    }

    /**
     * Performs the logic for switching the LDAP server connection.
     *
     * @param string|null $currentServer The server we are currently on.
     * @param string|null $wantedServer The server we want the connection to be on.
     * @param LdapOperationInterface $operation
     */
    protected function switchServerIfNeeded($currentServer, $wantedServer, $operation)
    {
        if ($operation instanceof AuthenticationOperation || strtolower($currentServer) == strtolower($wantedServer)) {
            return;
        }
        if ($this->connection->isBound()) {
            $this->connection->close();
        }
        $this->connection->connect(null, null, false, $wantedServer);
    }
}
