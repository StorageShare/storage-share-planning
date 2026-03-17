<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /**
     * @phpstan-return view-string
     */
    private function viewName(string $name): string
    {
        return $name;
    }
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view($this->viewName('layouts.guest'));
    }
}
