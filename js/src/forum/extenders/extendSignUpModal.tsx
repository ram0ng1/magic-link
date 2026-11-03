import { extend, override } from 'flarum/common/extend';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';

import { addCodeFields, createState, honeypotField, requestCode, resentAlert, submitButton, verifyCode } from '../utils/magicLink';

/**
 * O cadastro vira "nome de usuário e e-mail → código → conta criada". O fim é
 * o POST /register do próprio core, com o token do código no lugar da senha:
 * os campos que outras extensões põem no modal (termos, perfil…) vão junto.
 *
 * Com `attrs.token` (login social, ou o link do e-mail) o fluxo é o do core,
 * que já não pede senha.
 */
export default function extendSignUpModal() {
  extend('flarum/forum/components/SignUpModal', 'oninit', function (this: any) {
    this.magicLink = createState();
    this.magicLinkToken = null;

    this.magicLinkSend = (resend: boolean) => {
      const state = this.magicLink;

      this.loading = true;
      this.alertAttrs = null;

      requestCode(
        state,
        { type: 'signup', identification: this.email().trim(), username: this.username().trim(), remember: true },
        this.onerror.bind(this)
      ).then(() => {
        state.email = this.email().trim();
        this.loading = false;

        if (resend) this.alertAttrs = resentAlert();

        m.redraw.sync();
        this.$('[name=code]').trigger('select');
      }, this.loaded.bind(this));
    };
  });

  extend('flarum/forum/components/SignUpModal', 'fields', function (this: any, items: ItemList<Mithril.Children>) {
    if (this.attrs.token) return;

    const state = this.magicLink;

    items.remove('password');

    // Código conferido: sobra o cadastro do core (nome de usuário ainda pode ser corrigido).
    if (this.magicLinkToken) return;

    if (state.ref) {
      items.remove('username');
      items.remove('email');

      addCodeFields(items, state, {
        type: 'signup',
        loading: this.loading,
        onResend: () => this.magicLinkSend(true),
        onChangeEmail: () => {
          state.ref = null;
          this.alertAttrs = null;
        },
      });
    } else {
      items.add('magicLinkHoneypot', honeypotField(state), -100);
    }

    if (items.has('submit')) {
      items.setContent(
        'submit',
        submitButton(state.ref ? 'ramon-magic-link.forum.code.submit_button' : 'ramon-magic-link.forum.sign_up.submit_button', this.loading)
      );
    }
  });

  override('flarum/forum/components/SignUpModal', 'onsubmit', function (this: any, original: (e: SubmitEvent) => void, e: any) {
    if (this.attrs.token || this.magicLinkToken) return original(e);

    e.preventDefault();

    if (!this.magicLink.ref) {
      this.magicLinkSend(false);
      return;
    }

    this.loading = true;
    this.alertAttrs = null;

    verifyCode(this.magicLink, this.onerror.bind(this)).then((result) => {
      // O e-mail já tinha conta: o código era de login e a pessoa já entrou.
      if (result.action === 'login') {
        window.location.reload();
        return;
      }

      this.magicLinkToken = result.token;
      original(e);
    }, this.loaded.bind(this));
  });

  override('flarum/forum/components/SignUpModal', 'submitData', function (this: any, original: () => Record<string, unknown>) {
    const data = original();

    if (this.magicLinkToken) {
      delete data.password;
      data.token = this.magicLinkToken;
    }

    return data;
  });
}
