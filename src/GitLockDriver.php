<?php

declare(strict_types=1);

namespace ChrisHarrison\LockGitDriver;

use ChrisHarrison\Lock\Lock;
use ChrisHarrison\Lock\LockDriver\LockDriver;
use ChrisHarrison\Lock\LockSerialiser\LockSerialiser;
use Cz\Git\GitRepository;
use Exception;
use function file_exists;
use function file_get_contents;
use function is_dir;
use Throwable;

final class GitLockDriver implements LockDriver
{
    private $lockSerialiser;
    private $repoUrl;
    private $repoPath;
    private $lockPath;
    private $repo;

    public function __construct(
        LockSerialiser $lockSerialiser,
        string $repoUrl,
        string $repoPath,
        string $lockPath
    ) {
        $this->lockSerialiser = $lockSerialiser;
        $this->repoUrl = $repoUrl;
        $this->repoPath = $repoPath;
        $this->lockPath = $lockPath;
        $this->repo = null;
    }

    public function read(): Lock
    {
        $path = $this->repoPath . '/' . $this->lockPath;

        $repo = $this->repo();
        $repo->pull();

        if (!file_exists($path)) {
            return Lock::null();
        }
        $file = file_get_contents($path);
        if ($file === false) {
            return Lock::null();
        }
        return $this->lockSerialiser->unserialise($file);
    }

    public function write(Lock $lock): void
    {
        $path = $this->repoPath . '/' . $this->lockPath;
        $repo = $this->repo();
        file_put_contents($path, $this->lockSerialiser->serialise($lock));
        $repo->addFile($this->lockPath);
        $repo->commit('Automated lock file update');
        $repo->push(null, ['--force']);
    }

    private function repo(): GitRepository
    {
        if ($this->repo !== null)
        {
            return $this->repo;
        }
        if (is_dir($this->repoPath . '/.git')) {
            return $this->repo = new GitRepository($this->repoPath);
        } else {
            return $this->repo = GitRepository::cloneRepository($this->repoUrl, $this->repoPath);
        }
    }
}
