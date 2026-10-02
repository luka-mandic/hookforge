<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProjectList extends Component
{
    public function render(): View
    {
        $projects = Auth::user()?->projects()->latest()->get() ?? collect();

        return view('livewire.project-list', [
            'projects' => $projects,
        ]);
    }
}
