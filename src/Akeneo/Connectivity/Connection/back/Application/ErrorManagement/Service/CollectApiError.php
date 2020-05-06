<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Application\ErrorManagement\Service;

use Akeneo\Connectivity\Connection\Application\ConnectionContextInterface;
use Akeneo\Connectivity\Connection\Domain\ErrorManagement\Model\Write\BusinessError;
use Akeneo\Connectivity\Connection\Domain\ErrorManagement\Persistence\Repository\BusinessErrorRepository;
use Akeneo\Connectivity\Connection\Domain\Settings\Model\ValueObject\FlowType;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @copyright 2020 Akeneo SAS (http://www.akeneo.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class CollectApiError
{
    /** @var BusinessErrorRepository */
    private $repository;

    /** @var ConnectionContextInterface */
    private $connectionContext;

    /** @var ExtractErrorsFromHttpExceptionInterface */
    private $extractErrorsFromHttpException;

    private $errors = [];

    public function __construct(
        ConnectionContextInterface $connectionContext,
        BusinessErrorRepository $repository,
        ExtractErrorsFromHttpExceptionInterface $extractErrorsFromHttpException
    ) {
        $this->repository = $repository;
        $this->connectionContext = $connectionContext;
        $this->extractErrorsFromHttpException = $extractErrorsFromHttpException;
    }

    public function collectFromHttpException(HttpException $httpException): void
    {
        $errors = $this->extractErrorsFromHttpException->extractAll($httpException);
        $this->collect($errors);
    }

    public function flush(): void
    {
        if (0 === count($this->errors)) {
            return;
        }

        $this->repository->bulkInsert($this->errors);
    }

    /**
     * @param string[] $errors
     */
    private function collect(array $errors): void
    {
        $connection = $this->connectionContext->getConnection();
        if (null === $connection) {
            return;
        }

        if (false === $this->connectionContext->isCollectable() || FlowType::DATA_SOURCE !== (string) $connection->flowType()) {
            return;
        }

        $newErrors = [];
        foreach ($errors as $error) {
            $newErrors[] = new BusinessError($connection->code(), $error);
        }

        $this->errors = array_merge($this->errors, $newErrors);
    }
}