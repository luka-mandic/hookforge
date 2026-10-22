<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Services\Concerns\ReadsAttributes;
use Illuminate\Support\Str;

class ProjectService
{
    use ReadsAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, array $attributes): Project
    {
        $name = $this->requiredString($attributes, 'name');

        $project = new Project([
            'user_id' => $user->getKey(),
            'name' => $name,
            'description' => $this->optionalString($attributes, 'description'),
            'status' => 'active',
        ]);

        $project->slug = $this->resolveUniqueSlug($user, $name);

        $user->projects()->save($project);

        return $project->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Project $project, array $attributes): Project
    {
        $name = $this->requiredString($attributes, 'name');

        $changes = [
            'name' => $name,
            'description' => $this->optionalString($attributes, 'description'),
            'status' => $this->optionalString($attributes, 'status', $project->status),
        ];

        $changes['slug'] = $this->resolveUniqueSlug($project->user()->firstOrFail(), $name, $project->id);

        $project->fill($changes);
        $project->save();

        return $project->refresh();
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    protected function resolveUniqueSlug(User $user, string $name, ?string $ignoreProjectId = null): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $counter = 1;

        while ($user->projects()->where('slug', $slug)->when($ignoreProjectId, fn ($query) => $query->whereKeyNot($ignoreProjectId))->exists()) {
            $slug = sprintf('%s-%d', $base, $counter);
            $counter++;
        }

        return $slug;
    }
}
