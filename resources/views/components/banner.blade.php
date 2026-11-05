@use('Illuminate\Support\Facades\Route')
@use('Pijler\Impersonation\Impersonation')

@php($impersonation = Impersonation::data())

@if ($impersonation)
    <div role="status" {{ $attributes->merge(['style' => 'display: flex; gap: 1rem; align-items: center; justify-content: center; padding: 0.5rem 1rem; color: #fff; background: #b91c1c; font-size: 0.875rem;']) }}>
        <span>{{ __('You are impersonating :target.', ['target' => data_get($impersonation, 'target.name') ?? data_get($impersonation, 'target.id')]) }}</span>

        @if (Route::has('impersonation.stop'))
            <form method="POST" action="{{ route('impersonation.stop') }}" style="margin: 0;">
                @csrf
                @method('DELETE')

                <button type="submit" style="padding: 0.125rem 0.75rem; color: inherit; background: none; border: 1px solid currentColor; border-radius: 0.25rem; cursor: pointer;">
                    {{ __('Stop impersonating') }}
                </button>
            </form>
        @endif
    </div>
@endif
