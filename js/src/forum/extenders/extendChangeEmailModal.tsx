import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';
import type RequestError from 'flarum/common/utils/RequestError';

import { addCodeFields, createState, reauthTicket, requestCode, resentAlert, submitButton } from '../utils/magicLink';

/**
 * Trocar o e-mail pede a senha atual. Aqui um código vai para o e-mail atual e
 * o bilhete dele segue no lugar da senha (o servidor o confere no password
 * checker da extensão). Vale também com a senha permitida: quem se cadastrou
 * pelo código não tem senha nenhuma.
 */
export default function extendChangeEmailModal() {
  extend('flarum/forum/components/ChangeEmailModal', 'oninit', function (this: any) {
    this.magicLink = createState();

    this.magicLinkSend = (resend: boolean) => {
      this.loading = true;
      this.alertAttrs = null;

      requestCode(this.magicLink, { type: 'reauth' }, this.onerror.bind(this)).then(() => {
        this.magicLink.email = app.session.user!.email() || '';
        this.loading = false;

        if (resend) this.alertAttrs = resentAlert();

        m.redraw.sync();
        this.$('[name=code]').trigger('select');
      }, this.loaded.bind(this));
    };
  });

  extend('flarum/forum/components/ChangeEmailModal', 'fields', function (this: any, items: ItemList<Mithril.Children>) {
    if (this.success) return;

    items.remove('password');

    if (this.magicLink.ref) {
      addCodeFields(items, this.magicLink, { type: 'reauth', loading: this.loading, onResend: () => this.magicLinkSend(true) });

      // O core adiciona tudo com prioridade 0: o novo e-mail fica em cima do código.
      if (items.has('email')) items.setPriority('email', 50);
      if (items.has('submit')) items.setPriority('submit', 0);
    }

    if (items.has('submit')) {
      items.setContent(
        'submit',
        submitButton(
          this.magicLink.ref ? 'ramon-magic-link.forum.change_email.submit_button' : 'ramon-magic-link.forum.change_email.send_code_button',
          this.loading
        )
      );
    }
  });

  override('flarum/forum/components/ChangeEmailModal', 'onsubmit', function (this: any, original: (e: SubmitEvent) => void, e: any) {
    // E-mail igual: o core só fecha o modal.
    if (this.email() === app.session.user!.email()) return original(e);

    e.preventDefault();

    if (!this.magicLink.ref) {
      this.magicLinkSend(false);
      return;
    }

    this.password(reauthTicket(this.magicLink));
    original(e);
  });

  override('flarum/forum/components/ChangeEmailModal', 'onerror', function (this: any, original: (error: RequestError) => void, error: any) {
    original(error);

    // O 401 aqui é o código, não uma senha.
    if (error.status === 401 && this.alertAttrs) {
      this.alertAttrs.content = app.translator.trans('ramon-magic-link.forum.code.invalid_message');
      m.redraw();
    }
  });
}
