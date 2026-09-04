@once
    <script>
        window.noteReveal = function () {
            return {
                reveal: null,
                _hideTimer: null,
                setReveal(value) {
                    clearTimeout(this._hideTimer);
                    this.reveal = this.reveal === value ? null : value;
                    if (this.reveal !== null) {
                        this._hideTimer = setTimeout(() => {
                            this.reveal = null;
                        }, 30000);
                    }
                },
            };
        };
    </script>
@endonce
