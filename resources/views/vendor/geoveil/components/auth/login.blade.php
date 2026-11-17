@props(['login'])
@php($definition = $login->jsonSerialize())
<main {{ $attributes->class(['gv-auth']) }} data-layout="{{ $definition['layout'] }}" data-theme="{{ $definition['theme'] }}" style="--gv-auth-accent: {{ $definition['color'] ?? 'var(--gv-primary, #0f766e)' }}">
    @if($definition['layout'] === 'fullscreen' && $definition['image'])
        <div class="gv-auth-backdrop" aria-hidden="true"><img src="{{ $definition['image'] }}" alt=""></div>
    @endif
    @if($definition['layout'] === 'split')
        <aside class="gv-auth-visual" aria-hidden="true">
            @if($definition['image'])<img src="{{ $definition['image'] }}" alt="">@endif
            @if($definition['brand'])<span>{{ $definition['brand'] }}</span>@endif
        </aside>
    @endif
    <section class="gv-auth-card" aria-labelledby="gv-auth-title">
        @if($definition['logo'])<img class="gv-auth-logo" src="{{ $definition['logo'] }}" alt="{{ $definition['brand'] ?? '' }}">@endif
        @if($definition['brand'])<p class="gv-auth-brand">{{ $definition['brand'] }}</p>@endif
        <h1 id="gv-auth-title">{{ $definition['title'] }}</h1>
        @if($definition['subtitle'])<p class="gv-auth-subtitle">{{ $definition['subtitle'] }}</p>@endif

        @if(session('status'))<p class="gv-auth-status" role="status">{{ session('status') }}</p>@endif
        @if(isset($errors) && $errors->any())
            <div class="gv-auth-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ $definition['action'] ?? url()->current() }}" class="gv-auth-form">
            @csrf
            <label for="gv-auth-identity">{{ $definition['identity']['label'] }}</label>
            <input id="gv-auth-identity" name="{{ $definition['identity']['name'] }}" type="{{ $definition['identity']['type'] }}" value="{{ old($definition['identity']['name']) }}" autocomplete="username" required autofocus>

            <label for="gv-auth-password">{{ $definition['passwordLabel'] }}</label>
            <input id="gv-auth-password" name="password" type="password" autocomplete="current-password" required>

            @if($definition['twoFactor']['enabled'])
                <label for="gv-auth-two-factor">{{ $definition['twoFactor']['label'] }}</label>
                <input id="gv-auth-two-factor" name="code" type="text" inputmode="numeric" autocomplete="one-time-code">
            @endif

            <div class="gv-auth-options">
                @if($definition['remember'])<label class="gv-auth-remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Manter conectado</label>@endif
                @if($definition['forgotPasswordUrl'])<a href="{{ $definition['forgotPasswordUrl'] }}">Esqueci minha senha</a>@endif
            </div>
            <button type="submit">{{ $definition['submitLabel'] }}</button>
        </form>

        @if($definition['providers'])
            <div class="gv-auth-providers" aria-label="Outras formas de entrar">
                @foreach($definition['providers'] as $provider)<a href="{{ $provider['url'] }}">{{ $provider['label'] }}</a>@endforeach
            </div>
        @endif
        @if($definition['registerUrl'])<p class="gv-auth-register">Não tem uma conta? <a href="{{ $definition['registerUrl'] }}">Criar conta</a></p>@endif
    </section>
</main>
