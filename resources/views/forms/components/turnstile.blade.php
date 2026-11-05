<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @assets
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    @endassets

    <div x-data="{
        state: $wire.$entangle(@js($getStatePath())),
        widgetId: null,
        retryTimer: null,
    
        init() {
            const renderWidget = () => {
                if (!window.turnstile || this.widgetId !== null) {
                    return false;
                }
    
                this.widgetId = window.turnstile.render(this.$refs.widget, {
                    sitekey: @js($getSiteKey()),
                    action: @js($getTurnstileAction()),
                    theme: @js($getTheme()),
                    size: @js($getSize()),
                    appearance: @js($getAppearance()),
                    callback: (token) => this.state = token,
                    'expired-callback': () => this.state = '',
                    'error-callback': () => this.state = '',
                });
    
                return true;
            };
    
            if (!renderWidget()) {
                this.retryTimer = window.setInterval(() => {
                    if (renderWidget()) {
                        window.clearInterval(this.retryTimer);
                        this.retryTimer = null;
                    }
                }, 150);
            }
        },
    
        reset() {
            this.state = '';
    
            if (window.turnstile && this.widgetId !== null) {
                window.turnstile.reset(this.widgetId);
            }
        },
    
        destroy() {
            if (this.retryTimer !== null) {
                window.clearInterval(this.retryTimer);
            }
    
            if (window.turnstile && this.widgetId !== null) {
                window.turnstile.remove(this.widgetId);
            }
        },
    }" x-on:eii-turnstile-reset.window="reset()">
        <div x-ref="widget"></div>
        <input type="hidden" x-model="state" />
    </div>
</x-dynamic-component>
