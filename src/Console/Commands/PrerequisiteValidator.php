<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Console\Commands;

use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class PrerequisiteCheck
{
    /**
     * @param  string  $name  Executable name (e.g. 'git', 'cmake')
     * @param  string  $min  Minimum version (e.g. '2.0', '3.12')
     * @param  string  $versionFlag  Flag to get version (e.g. '--version')
     * @param  string  $versionPattern  Regex with one capture group for the version
     * @param  string  $installHint  Help text when missing or too old
     * @param  string  $label  Display label (defaults to $name)
     * @param  list<string>  $alternatives  Alternative executable names to try
     */
    public function __construct(
        public string $name,
        public string $min,
        public string $versionFlag,
        public string $versionPattern,
        public string $installHint,
        public string $label = '',
        public array $alternatives = [],
    ) {}
}

final class PrerequisiteValidator
{
    /** @return list<PrerequisiteCheck> */
    private static function libraryChecks(): array
    {
        return [
            new PrerequisiteCheck('git', '2.0', '--version', '/git version (\d+\.\d+\.\d+)/', 'Install git: https://git-scm.com/book/en/v2/Getting-Started-Installing-Git'),
            new PrerequisiteCheck('cmake', '3.12', '--version', '/cmake version (\d+\.\d+\.\d+)/', 'Install cmake: https://cmake.org/install/'),
            new PrerequisiteCheck('make', '3.81', '--version', '/GNU Make (\d+\.\d+)/', 'Install make: sudo apt install build-essential (Ubuntu) or xcode-select --install (macOS)'),
            new PrerequisiteCheck('cargo', '1.79', '--version', '/cargo (\d+\.\d+\.\d+)/', 'Install Rust: curl --proto "=https" --tlsv1.2 -sSf https://sh.rustup.rs | sh', 'Rust (cargo)'),
            new PrerequisiteCheck('g++', '10', '--version', '/(\d+)\.\d+\.\d+/', 'Install GCC 10+: sudo apt install g++-10 (Ubuntu) or brew install gcc (macOS)', 'C++ compiler (g++/clang++)', ['clang++']),
        ];
    }

    /** @return list<PrerequisiteCheck> */
    private static function modelChecks(): array
    {
        return [
            new PrerequisiteCheck('git', '2.0', '--version', '/git version (\d+\.\d+\.\d+)/', 'Install git: https://git-scm.com/book/en/v2/Getting-Started-Installing-Git'),
            new PrerequisiteCheck('git-lfs', '2.0', 'version', '/git-lfs\/(\d+\.\d+\.\d+)/', 'Install git-lfs: sudo apt install git-lfs (Ubuntu) or brew install git-lfs (macOS), then git lfs install'),
        ];
    }

    public function validateLibraryPrerequisites(SymfonyStyle $io): bool
    {
        $io->text('Checking prerequisites...');

        return $this->runChecks($io, self::libraryChecks());
    }

    public function validateModelPrerequisites(SymfonyStyle $io): bool
    {
        $io->text('Checking prerequisites...');

        return $this->runChecks($io, self::modelChecks());
    }

    public function findExecutable(string $name): ?string
    {
        $homeDir = is_string($_SERVER['HOME'] ?? null) ? $_SERVER['HOME'] : '';

        $projectVenvs = [
            getcwd().'/.venv/bin/'.$name,
            getcwd().'/venv/bin/'.$name,
            getcwd().'/env/bin/'.$name,
        ];

        $homeVenvs = [];

        if ($homeDir !== '') {
            $homeVenvs = [
                $homeDir.'/.venv/bin/'.$name,
                $homeDir.'/venv/bin/'.$name,
                $homeDir.'/env/bin/'.$name,
                $homeDir.'/myenv/bin/'.$name,
            ];
        }

        if ($homeDir !== '' && is_dir($homeDir)) {
            $homeVenvs = array_merge($homeVenvs, $this->findVirtualenvBinaries($homeDir, $name));
        }

        $allPaths = array_merge($projectVenvs, $homeVenvs);

        foreach ($allPaths as $path) {
            $expanded = $this->expandTilde($path);
            if (file_exists($expanded) && is_executable($expanded)) {
                return $expanded;
            }
        }

        $systemPaths = [
            '/usr/bin/'.$name,
            '/usr/local/bin/'.$name,
            '/opt/homebrew/bin/'.$name,
        ];

        if ($homeDir !== '') {
            $systemPaths[] = $homeDir.'/.cargo/bin/'.$name;
            $systemPaths[] = $homeDir.'/.local/bin/'.$name;
        }

        foreach ($systemPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        $result = shell_exec("which {$name} 2>/dev/null");

        if (is_string($result) && trim($result) !== '') {
            return trim($result);
        }

        return null;
    }

    public function expandTilde(string $path): string
    {
        $homeDir = is_string($_SERVER['HOME'] ?? null) ? $_SERVER['HOME'] : '';

        if ($homeDir !== '' && str_starts_with($path, '~/')) {
            return $homeDir.'/'.substr($path, 2);
        }

        return $path;
    }

    public function resolvePython(string $explicitPath): ?string
    {
        if ($explicitPath !== '') {
            $expanded = $this->expandTilde($explicitPath);

            if (file_exists($expanded) && is_executable($expanded)) {
                return $expanded;
            }

            return null;
        }

        return $this->findExecutable('python3') ?? $this->findExecutable('python');
    }

    /**
     * @return list<string>
     */
    private function findVirtualenvBinaries(string $baseDir, string $name): array
    {
        $results = [];
        $candidates = ['myenv', '.venv', 'venv', 'env', '.virtualenvs'];

        foreach ($candidates as $candidate) {
            $binPath = $baseDir.'/'.$candidate.'/bin/'.$name;
            if (file_exists($binPath) && is_executable($binPath)) {
                $results[] = $binPath;
            }
        }

        $virtualenvsDir = $baseDir.'/.virtualenvs';
        if (is_dir($virtualenvsDir)) {
            $entries = scandir($virtualenvsDir);
            if ($entries !== false) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $binPath = $virtualenvsDir.'/'.$entry.'/bin/'.$name;
                    if (file_exists($binPath) && is_executable($binPath)) {
                        $results[] = $binPath;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @param  list<PrerequisiteCheck>  $checks
     */
    private function runChecks(SymfonyStyle $io, array $checks): bool
    {
        $allPassed = true;

        foreach ($checks as $check) {
            $label = $check->label !== '' ? $check->label : $check->name;

            $path = $this->findExecutable($check->name);

            if ($path === null && $check->alternatives !== []) {
                foreach ($check->alternatives as $alt) {
                    $path = $this->findExecutable($alt);
                    if ($path !== null) {
                        break;
                    }
                }
            }

            if ($path === null) {
                $io->error("  ✗ {$label}: not found");
                $io->text("    → {$check->installHint}");
                $allPassed = false;

                continue;
            }

            $version = $this->getToolVersion($path, $check->versionFlag, $check->versionPattern);

            if ($version === null) {
                $io->text("  ⚠ {$label}: {$path} (version unknown, minimum {$check->min})");

                continue;
            }

            if ($this->compareVersion($version, $check->min) < 0) {
                $io->error("  ✗ {$label}: {$path} (v{$version}, minimum v{$check->min})");
                $io->text("    → {$check->installHint}");
                $allPassed = false;

                continue;
            }

            $io->text("  ✓ {$label}: {$path} (v{$version})");
        }

        return $allPassed;
    }

    private function getToolVersion(string $path, string $versionFlag, string $pattern): ?string
    {
        $output = shell_exec(escapeshellarg($path).' '.escapeshellarg($versionFlag).' 2>/dev/null');

        if (! is_string($output) || trim($output) === '') {
            return null;
        }

        if (preg_match($pattern, $output, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function compareVersion(string $actual, string $required): int
    {
        $actualParts = array_map('intval', explode('.', $actual));
        $requiredParts = array_map('intval', explode('.', $required));

        $maxParts = max(count($actualParts), count($requiredParts));

        $actualParts = array_pad($actualParts, $maxParts, 0);
        $requiredParts = array_pad($requiredParts, $maxParts, 0);

        for ($i = 0; $i < $maxParts; $i++) {
            if ($actualParts[$i] < $requiredParts[$i]) {
                return -1;
            }
            if ($actualParts[$i] > $requiredParts[$i]) {
                return 1;
            }
        }

        return 0;
    }
}
