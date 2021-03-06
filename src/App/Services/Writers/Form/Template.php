<?php

namespace LaravelEnso\Cli\App\Services\Writers\Form;

use Illuminate\Support\Str;
use LaravelEnso\Cli\App\Contracts\StubProvider;
use LaravelEnso\Cli\App\Services\Choices;
use LaravelEnso\Cli\App\Services\Writers\Helpers\Directory;
use LaravelEnso\Cli\App\Services\Writers\Helpers\Path;
use LaravelEnso\Cli\App\Services\Writers\Helpers\Stub;
use LaravelEnso\Helpers\App\Classes\Obj;

class Template implements StubProvider
{
    private Obj $model;
    private string $group;
    private string $rootSegment;

    public function __construct(Choices $choices)
    {
        $this->model = $choices->get('model');
        $this->group = $choices->get('permissionGroup')->get('name');
        $this->rootSegment = $choices->params()->get('rootSegment');
    }

    public function prepare(): void
    {
        Directory::prepare($this->path());
    }

    public function filePath(): string
    {
        $name = Str::camel($this->model->get('name'));

        return $this->path("{$name}.json");
    }

    public function fromTo(): array
    {
        return ['${permissionGroup}' => $this->group];
    }

    public function stub(): string
    {
        return Stub::get('template');
    }

    private function path(?string $filename = null): string
    {
        return Path::get([$this->rootSegment, 'Forms', 'Templates'], $filename);
    }
}
