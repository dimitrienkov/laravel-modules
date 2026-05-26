<?php

declare(strict_types=1);

namespace DimitrienkoV\LaravelModules\Application\Support;

use DimitrienkoV\LaravelModules\Contracts\ModuleStateRepositoryInterface;

final readonly class PartialModuleRollback
{
    public function __construct(
        private ModuleDirectoryOperations $directoryOps,
        private ModuleStateRepositoryInterface $stateRepository,
    ) {
    }

    /**
     * @return string Cleanup note (empty if state cleanup succeeded)
     */
    public function rollback(string $moduleName, string $targetPath): string
    {
        $this->directoryOps->tryDeleteDirectory($targetPath);

        try {
            $this->stateRepository->delete($moduleName);
        } catch (\Throwable $cleanupError) {
            return ' State cleanup also failed: ' . $cleanupError->getMessage();
        }

        return '';
    }
}
