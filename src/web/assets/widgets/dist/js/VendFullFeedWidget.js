/** global: Craft */
/** global: Garnish */
/** global: $ */
/**
 * Full Feed Widget Class
 */
if (typeof Craft.Vend === typeof undefined) {
    Craft.Vend = {};
}

Craft.Vend.FullFeedWidget = Garnish.Base.extend({

    $widget: null,
    $body: null,
    $btn: null,
    working: false,

    init: function(settings) {
        this.setSettings(settings, this.defaults);

        this.$widget = $('#widget' + this.settings.widgetId);
        this.$body = this.$widget.find('.body:first');
        this.initBtn();
    },

    initBtn: function() {
        this.$btn = this.$body.find('.btn:first');
        this.addListener(this.$btn, 'click', function() {
            this.startFullSync();
        });
    },

    startFullSync: function() {
        if (this.working) {
            return;
        }

        this.working = true;
        this.$widget.addClass('loading');
        this.$btn.addClass('disabled');

        Craft.postActionRequest('vend/feeds/run', {}, $.proxy(function(response, textStatus) {
            this.working = false;
            this.$widget.removeClass('loading');
            this.$btn.removeClass('disabled');

            if (textStatus === 'success') {
                if (response.success) {
                    Craft.cp.runQueue();
                    Craft.cp.displayNotice('Full sync started.');
                } else if (response.error) {
                    Craft.cp.displayError(response.error);
                } else {
                    Craft.cp.displayError('Couldn’t start sync operation.');
                }
            }
        }, this));
    },

    defaults: {
        widgetId: null
    }
});