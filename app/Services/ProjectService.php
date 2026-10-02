<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

class ProjectService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(User $user, array $attributes): Project
    {
        $name = (string) $attributes['name'];

        $project = new Project([
            'user_id' => $user->getKey(),
            'name' => $name,
            'description' => isset($attributes['description']) && is_string($attributes['description']) ? $attributes['description'] : null,
            'status' => 'active',
        ]);

        $project->slug = $this->resolveUniqueSlug($user, $name);

        $user->projects()->save($project);

        return $project->fresh();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Project $project, array $attributes): Project
    {
        $name = (string) $attributes['name'];

        $changes = [
            'name' => $name,
            'description' => array_key_exists('description', $attributes) && is_string($attributes['description']) ? $attributes['description'] : null,
        ];

        if (array_key_exists('status', $attributes) && is_string($attributes['status'])) {
            $changes['status'] = $attributes['status'];
        }

        $changes['slug'] = $this->resolveUniqueSlug($project->user, $name, $project->id);

        $project->fill($changes);
        $project->save();

        return $project->fresh();
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

        while ($user->projects()->where('slug', $slug)->when($ignoreProjectId, fn($query) => $query->whereKeyNot($ignoreProjectId))->exists()) {
            $slug = sprintf('%s-%d', $base, $counter);
            $counter++;
        }

        return $slug;
    }
}
