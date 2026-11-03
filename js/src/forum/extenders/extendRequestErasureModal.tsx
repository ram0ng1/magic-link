import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';

import { addCodeFields, createState, reauthTicket, requestCode, submitButton } from '../utils/magicLink';

/**
 * A flarum/gdpr pede a senha para apagar a conta de quem não tem login externo.
 * Como na troca de e-mail, o código enviado para o e-mail da conta vai no
 * lugar da senha. Sem a gdpr o módulo não carrega e nada disto roda.
 */
export default function extendRequestErasureModal() {
  extend('ext:flarum/gdpr/forum/components/RequestErasureModal', 'oninit', function (this: any) {
    this.magicLink = createState();

    this.magicLinkSend = () => {
      this.loading = true;
      this.alertAttrs = null;

      requestCode(this.magicLink, { type: 'reauth' }, this.onerror.bind(this)).then(() => {
        this.magicLink.email = app.session.user?.email() || '';
        this.loading = false;

        m.redraw.sync();
        this.$('[name=code]').trigger('select');
      }, this.loaded.bind(this));
    };
  });

  extend('ext:flarum/gdpr/forum/components/RequestErasureModal', 'fields', function (this: any, items: ItemList<Mithril.Children>) {
    // Sem o campo de senha (pedido já feito, ou conta com login externo), nada muda.
    this.magicLinkReauth = items.has('password');

    if (!this.magicLinkReauth) return;

    items.remove('password');

    // O texto da gdpr (o que acontece com a conta) fica acima do código.
    if (items.has('text')) items.setPriority('text', 50);

    if (this.magicLink.ref) {
      addCodeFields(items, this.magicLink, { type: 'reauth', loading: this.loading, onResend: () => this.magicLinkSend() });
    } else if (items.has('submit')) {
      items.setContent('submit', submitButton('ramon-magic-link.forum.change_email.send_code_button', this.loading));
    }
  });

  override('ext:flarum/gdpr/forum/components/RequestErasureModal', 'onsubmit', function (this: any, original: (e: Event) => void, e: any) {
    if (!this.magicLinkReauth) return original(e);

    e.preventDefault();

    if (!this.magicLink.ref) {
      this.magicLinkSend();
      return;
    }

    this.password(reauthTicket(this.magicLink));
    original(e);
  });
}
