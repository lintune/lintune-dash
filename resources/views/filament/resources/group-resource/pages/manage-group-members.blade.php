<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Current members --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                <h3 class="fi-section-header-heading text-base font-semibold text-gray-950 dark:text-white">
                    Current Members ({{ count($currentMembers) }})
                </h3>
            </div>

            @if(empty($currentMembers))
                <p class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">No members yet.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($currentMembers as $m)
                        <li class="flex items-center gap-3 px-6 py-3">
                            <x-heroicon-o-user class="h-4 w-4 shrink-0 text-gray-400" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $m['name'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $m['email'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Add members --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-x-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                <h3 class="fi-section-header-heading text-base font-semibold text-gray-950 dark:text-white">
                    Add / Remove Members
                </h3>
            </div>

            <div class="px-6 py-4">
                @if(empty($availableUsers) && empty($currentMembers))
                    <p class="text-sm text-gray-500 dark:text-gray-400">No users available.</p>
                @else
                    <form wire:submit="save" class="space-y-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Select all users that should be in this group. Unchecking a current member removes them.
                        </p>

                        <div class="space-y-2 max-h-80 overflow-y-auto">
                            @php
                                $allUsers = array_merge(
                                    array_map(fn($m) => ['id' => $m['user_id'], 'name' => $m['name'], 'email' => $m['email'], 'current' => true], $currentMembers),
                                    array_map(fn($u) => array_merge($u, ['current' => false]), $availableUsers)
                                );
                            @endphp

                            @foreach($allUsers as $user)
                                @php $value = $user['id'] . '|' . $user['email']; @endphp
                                <label class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="selectedMembers"
                                        value="{{ $value }}"
                                        @if($user['current']) checked @endif
                                        class="rounded border-gray-300 text-primary-600 dark:border-gray-600"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user['name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user['email'] }}</p>
                                    </div>
                                    @if($user['current'])
                                        <span class="text-xs text-success-600 dark:text-success-400">member</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>

                        <button
                            type="submit"
                            class="fi-btn fi-btn-size-md fi-btn-color-primary fi-color-primary rounded-lg px-4 py-2 text-sm font-semibold"
                        >
                            Save Members
                        </button>
                    </form>
                @endif
            </div>
        </div>

    </div>
</x-filament-panels::page>
