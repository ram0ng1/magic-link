import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';

import {
  addCodeFields,
  createState,
  honeypotField,
  recaptchaErrorAlert,
  recaptchaFor,
  recaptchaIsCheckbox,
  recaptchaParams,
  recaptchaReset,
  recaptchaToken,
  requestCode,
  resentAlert,
  submitButton,
  verifyCode,
} from '../utils/magicLink';

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

      // Com o checkbox da fof/recaptcha, um novo código pede o captcha de novo: volta ao formulário.
      if (resend && recaptchaIsCheckbox(this, 'signup')) {
        state.ref = null;
        this.alertAttrs = { type: 'warning', content: app.translator.trans('ramon-magic-link.forum.recaptcha.resend_text') };
        return;
      }

      this.loading = true;
      this.alertAttrs = null;

      recaptchaParams(this, 'signup')
        .catch(() => {
          this.alertAttrs = recaptchaErrorAlert();
          throw new Error('recaptcha');
        })
        .then((captcha) =>
          requestCode(
            state,
            { type: 'signup', identification: this.email().trim(), username: this.username().trim(), remember: true, ...captcha },
            this.onerror.bind(this)
          )
        )
        .then(
          () => {
            state.email = this.email().trim();
            this.loading = false;

            if (resend) this.alertAttrs = resentAlert();

            m.redraw.sync();
            this.$('[name=code]').trigger('focus');
          },
          () => {
            recaptchaReset(this, 'signup');
            this.loaded();
          }
        );
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

      // O checkbox da fof/recaptcha já foi usado; volta no fim, para o /register.
      if (recaptchaIsCheckbox(this, 'signup')) items.remove('recaptcha');

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

      // O /register também pede o captcha da fof/recaptcha: o checkbox precisa ser
      // marcado de novo; o invisível e o v3 geram outro token, e a fof o usa.
      if (recaptchaIsCheckbox(this, 'signup')) {
        this.magicLinkToken = result.token;
        this.loaded();
        this.alertAttrs = { type: 'warning', content: app.translator.trans('ramon-magic-link.forum.recaptcha.finish_sign_up_text') };
        return;
      }

      const recaptcha = recaptchaFor(this, 'signup');

      if (!recaptcha) {
        this.magicLinkToken = result.token;
        original(e);
        return;
      }

      // Antes de trocar a tela: com os campos do cadastro de volta, o Mithril
      // recria o widget, e o execute() no novo falha enquanto ele carrega.
      recaptchaToken(recaptcha).then(
        (token) => {
          this.magicLinkToken = result.token;
          this.recaptchaToken = token;
          original(e);
        },
        () => {
          this.alertAttrs = recaptchaErrorAlert();
          this.loaded();
        }
      );
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
