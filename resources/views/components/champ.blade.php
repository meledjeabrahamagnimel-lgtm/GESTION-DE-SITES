@props(['label', 'model', 'type' => 'text', 'options' => null, 'width' => null, 'placeholder' => null, 'live' => false])

@php
    $wire = $live ? 'wire:model.live' : 'wire:model';
    $largeur = $width ? 'width:'.$width.'px;' : 'flex:1; min-width:150px;';
@endphp

@if ($type === 'checkbox')
    <label style="font-size:13px; display:flex; gap:6px; align-items:center; padding-bottom:9px; white-space:nowrap;">
        <input type="checkbox" wire:model="{{ $model }}">
        {{ $label }}
    </label>
@else
    <div style="display:flex; flex-direction:column; gap:4px; {{ $largeur }}">
        <label style="font-size:12.5px; font-weight:600; color:#4B4E55;">{{ $label }}</label>
        @if ($type === 'select')
            <select {{ $wire }}="{{ $model }}" style="padding:8px 10px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:14px; background:#fff;">
                @foreach ($options as $valeur => $libelle)
                    <option value="{{ $valeur }}">{{ $libelle }}</option>
                @endforeach
            </select>
        @else
            <input type="{{ $type }}" {{ $wire }}="{{ $model }}" placeholder="{{ $placeholder }}"
                style="padding:8px 10px; border:1px solid var(--th-ligne,#E2E0D8); border-radius:8px; font-size:14px;">
        @endif
        @error($model) <span style="color:#C8102E; font-size:12px;">{{ $message }}</span> @enderror
    </div>
@endif
