<?php

namespace LaravelEnso\StructureManager\app\Commands;

use Illuminate\Console\Command;
use LaravelEnso\Helpers\app\Classes\Obj;
use LaravelEnso\StructureManager\app\Writers\Helpers\Symbol;
use LaravelEnso\StructureManager\app\Classes\StructureWriter;
use LaravelEnso\StructureManager\app\Writers\RoutesGenerator;

class MakeEnsoStructure extends Command
{
    const Menu = [
        'Model',
        'Permission Group',
        'Permissions',
        'Menu',
        'Files',
        'Generate',
    ];

    protected $signature = 'enso:make:structure';
    protected $description = 'Create a new Laravel Enso Structure';

    private $choices;
    private $configured;

    public function handle()
    {
        $this->configured = collect();
        $this->setChoices();

        $this->info('Create a new Laravel Enso Structure');
        $this->line('');

        $this->index();
    }

    private function index()
    {
        $this->status();

        $choice = $this->choice('Choose element to configure', self::Menu);

        if ($this->choices()->contains($choice)) {
            $this->fill($choice);
        }

        if ($choice === $this->action()) {
            $this->attemptWrite();

            return;
        }

        $this->index();
    }

    private function fill($choice)
    {
        if ($this->missesRequired($choice)) {
            return;
        }

        $this->info(title_case($choice).' configuration:');

        $this->displayConfiguration($choice);

        if ($this->confirm('Configure '.title_case($choice))) {
            $this->updateConfiguration($choice);
        }
    }

    private function displayConfiguration($choice)
    {
        $config = $this->choices->get(camel_case($choice));

        collect($config->keys())
            ->each(function ($key) use ($config) {
                $this->line(
                    $key.' => '.(
                    is_bool($config->get($key))
                        ? Symbol::bool($config->get($key))
                        : $config->get($key)
                    )
                );
            });
    }

    private function updateConfiguration($choice)
    {
        $config = $this->choices->get(camel_case($choice));

        collect($config->keys())
            ->each(function ($key) use ($config, $choice) {
                $input = $this->input($config, $key);
                $config->set($key, $input);
            });

        if (!$this->configured->contains($choice)) {
            $this->configured->push($choice);
        }
    }

    private function input($config, $key)
    {
        $type = gettype($config->get($key));

        $value = is_bool($config->get($key))
            ? $this->confirm($key)
            : $this->anticipate($key, [$config->get($key)]);

        if ($this->isValid($type, $value)) {
            return $type === 'integer'
                ? intval($value)
                : $value;
        }

        $this->error($key.' must be of type '.$type);
        sleep(1);

        return $this->input($config, $key);
    }

    private function isValid($type, $value)
    {
        return $type === 'NULL'
            || ($type === 'integer' && (string) intval($value) === $value)
            || (gettype($value) === $type);
    }

    private function status()
    {
        $this->info('Current configuration status:');

        $this->choices()->each(function ($choice) {
            $this->line(
                $choice.' '.(Symbol::bool($this->configured->contains($choice)))
            );
        });
    }

    private function attemptWrite()
    {
        // $this->setTestConfig();

        if ($this->configured->isEmpty()) {
            $this->error('There is nothing configured yet!');
            $this->line('');
            sleep(1);
            $this->index();

            return;
        }

        $this->sanitize()
            ->filter()
            ->write()
            ->output();
    }

    private function sanitize()
    {
        $this->choices->get('model')->set(
            'name',
            ucfirst($this->choices->get('model')->get('name'))
        );

        return $this;
    }

    private function filter()
    {
        collect($this->choices->keys())
            ->each(function ($key) {
                if ($this->configured->first(function ($attribute) use ($key) {
                    return camel_case($attribute) === $key;
                }) === null) {
                    $this->choices->forget($key);
                }
            });

        if ($this->choices->has('files')) {
            collect($this->choices->get('files'))
                ->each(function ($chosen, $type) {
                    if (!$chosen) {
                        $this->choices->get('files')->forget($key);
                    }
                });
        }

        return $this;
    }

    private function write()
    {
        (new StructureWriter($this->choices))
            ->run();

        return $this;
    }

    private function output()
    {
        if ($this->choices->has('permissions')) {
            $routes = (new RoutesGenerator($this->choices))
                ->run();

            $this->info('Copy and paste the following code into your api.php routes file:');
            $this->line('');
            $this->warning($routes);
            $this->line('');
        }

        $this->info('The new structure is created, you can start playing');
        $this->line('');
    }

    private function warning($output)
    {
        return $this->line('<fg=yellow>'.$output.'</>');
    }

    private function missesRequired($choice)
    {
        $diff = $this->requires($choice)
            ->diff($this->configured);

        if ($diff->isNotEmpty()) {
            $this->warning('You must configure first: '.$diff->implode(', '));
            $this->line('');
            sleep(1);
        }

        return $diff->isNotEmpty();
    }

    private function attributes($choice)
    {
        return new Obj($this->config($choice, 'attributes'));
    }

    private function requires($choice)
    {
        return collect($this->config($choice, 'requires'));
    }

    private function config($choice, $param)
    {
        return config('enso.structures.'.camel_case($choice).'.'.$param);
    }

    private function action()
    {
        return collect(self::Menu)->pop();
    }

    private function choices()
    {
        return collect(self::Menu)->slice(0, -1);
    }

    private function setChoices()
    {
        $this->choices = new Obj();

        $this->choices()->each(function ($choice) {
            $this->choices->set(
                camel_case($choice),
                $this->attributes($choice)
            );
        });
    }

    private function setTestConfig()
    {
        $this->choices = new Obj(
            (array) json_decode(\File::get(__DIR__.'/../Writers/stubs/test.stub'))
        );

        $this->configured = collect($this->choices)->keys();

        collect($this->choices)->keys()
            ->each(function ($choice) {
                $this->choices->set(
                    $choice,
                    new Obj((array) $this->choices->get($choice))
                );
            });
    }
}
