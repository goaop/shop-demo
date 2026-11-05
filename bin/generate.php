<?php

declare(strict_types=1);

/**
 * Generates the bulk of the application: N modules below src/Module (woven: services and repositories are advised,
 * validators, DTOs and events are not) and N library packages below lib/ (outside the include paths, like vendor code).
 *
 * Usage: php bin/generate.php [modules=40] [--if-missing]
 *        --if-missing: only when the modules do not exist yet (composer's pre-autoload-dump hook)
 */

$arguments = array_values(array_filter(array_slice($argv, 1), static fn(string $argument): bool => $argument !== '--if-missing'));
$modules   = (int) ($arguments[0] ?? 40);
$root      = dirname(__DIR__);
if (in_array('--if-missing', $argv, true) && is_file($root . '/src/Module/ModuleRegistry.php')) {
    exit(0);
}

$removeTree = static function (string $dir) use (&$removeTree): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        is_dir($path) ? $removeTree($path) : unlink($path);
    }
    rmdir($dir);
};
$removeTree($root . '/src/Module');
$removeTree($root . '/lib');

$write = static function (string $file, string $code): void {
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n" . $code);
};

$services = [];
for ($n = 1; $n <= $modules; $n++) {
    $m   = sprintf('Module%02d', $n);
    $ns  = "App\\Module\\$m";
    $lib = sprintf('Lib\\Package%02d', $n);
    $dir = "$root/src/Module/$m";

    $write("$dir/{$m}Dto.php", <<<PHP
        namespace $ns;

        /**
         * Data transfer object of module $m
         */
        final readonly class {$m}Dto
        {
            /**
             * @param list<string> \$tags
             */
            public function __construct(
                public int \$id,
                public string \$title,
                public int \$amount,
                public array \$tags = [],
            ) {}

            public function withAmount(int \$amount): self
            {
                return new self(\$this->id, \$this->title, \$amount, \$this->tags);
            }
        }

        PHP);

    $write("$dir/{$m}Event.php", <<<PHP
        namespace $ns;

        final readonly class {$m}Event
        {
            public function __construct(
                public {$m}Dto \$subject,
                public string \$name,
            ) {}
        }

        PHP);

    $write("$dir/{$m}Validator.php", <<<PHP
        namespace $ns;

        /**
         * Validation rules of module $m: not advised, stays untransformed
         */
        final class {$m}Validator
        {
            private const int MAX_AMOUNT = 1_000_000;

            /** @return list<string> */
            public function validate({$m}Dto \$dto): array
            {
                \$errors = [];
                if (\$dto->id <= 0) {
                    \$errors[] = 'id must be positive';
                }
                if (\$dto->title === '' || strlen(\$dto->title) > 120) {
                    \$errors[] = 'title length must be 1..120';
                }
                if (\$dto->amount < 0 || \$dto->amount > self::MAX_AMOUNT) {
                    \$errors[] = 'amount out of range';
                }
                foreach (\$dto->tags as \$tag) {
                    if (!preg_match('/^[a-z0-9-]+$/', \$tag)) {
                        \$errors[] = "invalid tag \$tag";
                    }
                }

                return \$errors;
            }
        }

        PHP);

    $write("$dir/{$m}Repository.php", <<<PHP
        namespace $ns;

        /**
         * In-memory storage of module $m: every public method is advised by MonitoringAspect
         */
        class {$m}Repository
        {
            /** @var array<int, {$m}Dto> */
            private array \$items = [];

            public function __construct()
            {
                for (\$id = 1; \$id <= 10; \$id++) {
                    \$this->items[\$id] = new {$m}Dto(\$id, "$m item \$id", \$id * $n, ['tag-' . (\$id % 3)]);
                }
            }

            public function find(int \$id): ?{$m}Dto
            {
                return \$this->items[\$id] ?? null;
            }

            /** @return list<{$m}Dto> */
            public function findAll(): array
            {
                return array_values(\$this->items);
            }

            public function save({$m}Dto \$dto): void
            {
                \$this->items[\$dto->id] = \$dto;
            }

            public function count(): int
            {
                return count(\$this->items);
            }
        }

        PHP);

    $write("$dir/{$m}Service.php", <<<PHP
        namespace $ns;

        use $lib\\Formatter;

        /**
         * Application service of module $m: handle() is advised by SecurityAspect
         */
        class {$m}Service
        {
            private readonly {$m}Repository \$repository;

            private readonly {$m}Validator \$validator;

            /** @var list<{$m}Event> */
            private array \$events = [];

            public function __construct()
            {
                \$this->repository = new {$m}Repository();
                \$this->validator  = new {$m}Validator();
            }

            public function handle(int \$seed): int
            {
                \$dto = \$this->repository->find(1 + \$seed % 10) ?? throw new \\LogicException('No item');
                \$dto = \$dto->withAmount(\$this->recalculate(\$dto->amount, \$seed));
                if (\$this->validator->validate(\$dto) !== []) {
                    return 0;
                }
                \$this->repository->save(\$dto);
                \$this->events[] = new {$m}Event(\$dto, 'updated');

                return strlen(Formatter::describe(\$dto->title, \$dto->amount, \$dto->tags)) + \$this->repository->count();
            }

            public function summary(): string
            {
                \$total = 0;
                foreach (\$this->repository->findAll() as \$dto) {
                    \$total += \$dto->amount;
                }

                return Formatter::describe('$m', \$total, []);
            }

            private function recalculate(int \$amount, int \$seed): int
            {
                return (\$amount * 31 + \$seed) % 100_000;
            }
        }

        PHP);

    $libDir = "$root/lib/" . sprintf('Package%02d', $n);
    $write("$libDir/Str.php", <<<PHP
        namespace $lib;

        final class Str
        {
            public static function slug(string \$value): string
            {
                return strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', \$value), '-'));
            }

            public static function truncate(string \$value, int \$length): string
            {
                return strlen(\$value) > \$length ? substr(\$value, 0, \$length - 1) . '…' : \$value;
            }
        }

        PHP);
    $write("$libDir/Arr.php", <<<PHP
        namespace $lib;

        final class Arr
        {
            /**
             * @param list<string> \$values
             */
            public static function join(array \$values, string \$glue = ', '): string
            {
                return implode(\$glue, array_unique(\$values));
            }
        }

        PHP);
    $write("$libDir/Formatter.php", <<<PHP
        namespace $lib;

        final class Formatter
        {
            /**
             * @param list<string> \$tags
             */
            public static function describe(string \$title, int \$amount, array \$tags): string
            {
                return sprintf('%s [%s] %s', Str::truncate(Str::slug(\$title), 40), number_format(\$amount / 100, 2), Arr::join(\$tags));
            }
        }

        PHP);

    $services[] = "\\$ns\\{$m}Service::class";
}

$list = implode(",\n            ", $services);
$write("$root/src/Module/ModuleRegistry.php", <<<PHP
    namespace App\\Module;

    final class ModuleRegistry
    {
        /** @return list<class-string> */
        public static function services(): array
        {
            return [
                $list,
            ];
        }
    }

    PHP);

echo "Generated $modules modules (", $modules * 5 + 1, " classes in src/Module, ", $modules * 3, " classes in lib/)\n";
