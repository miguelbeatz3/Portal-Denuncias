<span @class(['portal-brand', 'portal-brand-entity' => filled($entityName ?? null)])>
    <img class="brand-primary" src="{{ asset('brand/portal-denuncias.svg') }}" alt="Portal de Denúncias" width="148" height="44">
    <img class="brand-secondary" src="{{ asset('brand/portal-denuncias-secundario.svg') }}" alt="Portal de Denúncias" width="148" height="44">
    <span>{{ $area }}@if (filled($entityName ?? null))<span class="portal-brand-entity-name">{{ $entityName }}</span>@endif</span>
</span>
