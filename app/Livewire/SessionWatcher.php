<?php

namespace App\Livewire;

use Livewire\Component;

class SessionWatcher extends Component
{
    public string $checkUrl;
    public string $logoutUrl;
    public string $csrfToken;
    public int    $expiresAt;

    public function mount(): void
    {
        $this->checkUrl   = route('session.check');
        $this->logoutUrl  = route('logout');
        $this->csrfToken  = csrf_token();
        $this->expiresAt  = (int) session('token_expires_at', 0);
    }

    public function render()
    {
        return view('livewire.session-watcher');
    }
}
