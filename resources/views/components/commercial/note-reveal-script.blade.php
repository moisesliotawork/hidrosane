@once
    <script>
        (function () {
            function registerNoteRevealStore() {
                if (typeof Alpine === 'undefined') {
                    return;
                }

                if (Alpine.store('noteReveal')) {
                    return;
                }

                Alpine.store('noteReveal', {
                    addressNoteId: null,
                    phoneNoteId: null,
                });
            }

            document.addEventListener('alpine:init', registerNoteRevealStore);

            if (window.Alpine) {
                registerNoteRevealStore();
            }
        })();

        window.noteReveal = function (opts) {
            opts = opts || {};
            const noteId = opts.noteId != null ? Number(opts.noteId) : null;

            return {
                noteId: noteId,
                _hideTimer: null,
                _hideTimerAddress: null,

                store() {
                    if (typeof Alpine === 'undefined') {
                        return null;
                    }

                    if (!Alpine.store('noteReveal')) {
                        Alpine.store('noteReveal', {
                            addressNoteId: null,
                            phoneNoteId: null,
                        });
                    }

                    return Alpine.store('noteReveal');
                },

                setReveal(value) {
                    const store = this.store();

                    if (!store || this.noteId === null) {
                        return;
                    }

                    if (value === 'address') {
                        clearTimeout(this._hideTimer);
                        clearTimeout(this._hideTimerAddress);

                        const closing = store.addressNoteId === this.noteId;
                        store.phoneNoteId = null;
                        store.addressNoteId = closing ? null : this.noteId;

                        if (!closing) {
                            this._hideTimerAddress = setTimeout(() => {
                                const s = this.store();
                                if (s && s.addressNoteId === this.noteId) {
                                    s.addressNoteId = null;
                                }
                            }, 30000);
                        }

                        return;
                    }

                    if (value === 'phone') {
                        clearTimeout(this._hideTimer);
                        clearTimeout(this._hideTimerAddress);

                        const closing = store.phoneNoteId === this.noteId;
                        store.addressNoteId = null;
                        store.phoneNoteId = closing ? null : this.noteId;

                        if (!closing) {
                            this._hideTimer = setTimeout(() => {
                                const s = this.store();
                                if (s && s.phoneNoteId === this.noteId) {
                                    s.phoneNoteId = null;
                                }
                            }, 30000);
                        }
                    }
                },

                phonesVisible() {
                    const store = this.store();
                    return !!(store && store.phoneNoteId === this.noteId);
                },

                addressVisible() {
                    const store = this.store();
                    return !!(store && store.addressNoteId === this.noteId);
                },
            };
        };
    </script>
@endonce
