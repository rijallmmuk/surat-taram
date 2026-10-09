<script>
    (() => {
        let holdTimer;
        let holdTarget;
        let suppressClickTarget;

        const actionButton = (element) => element instanceof Element
            ? element.closest('.fi-ta-actions .fi-icon-btn[aria-label]')
            : null;

        const cancelHold = () => {
            window.clearTimeout(holdTimer);
            holdTarget = null;
        };

        document.addEventListener('touchstart', (event) => {
            const button = actionButton(event.target);

            if (!button?._tippy) {
                return;
            }

            cancelHold();
            holdTarget = button;
            holdTimer = window.setTimeout(() => {
                if (holdTarget !== button) {
                    return;
                }

                button._tippy.show();
                suppressClickTarget = button;
            }, 450);
        }, { passive: true });

        document.addEventListener('touchmove', cancelHold, { passive: true });
        document.addEventListener('touchcancel', cancelHold, { passive: true });

        document.addEventListener('touchend', () => {
            cancelHold();

            if (!suppressClickTarget) {
                return;
            }

            const button = suppressClickTarget;
            window.setTimeout(() => button._tippy?.hide(), 1600);
            window.setTimeout(() => {
                if (suppressClickTarget === button) {
                    suppressClickTarget = null;
                }
            }, 500);
        }, { passive: true });

        document.addEventListener('click', (event) => {
            if (actionButton(event.target) !== suppressClickTarget) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            suppressClickTarget = null;
        }, true);
    })();
</script>
