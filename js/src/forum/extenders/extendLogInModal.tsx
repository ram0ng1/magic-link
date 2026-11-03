import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';

import { addCodeFields, createState, honeypotField, passwordless, requestCode, resentAlert, submitButton, verifyCode } from '../utils/magicLink';

/**
 * O login vira "e-mail → código". O modal continua sendo o LogInModal do core
 * (o Avocado envolve o content() dele e o Canela o desenha na página /login),
 * só os campos, o envio e o rodapé mudam.
 *
 * LogInModal é carregado sob demanda no 2.x, então é estendido pelo caminho do módulo.
 */
export default function extendLogInModal() {
  extend('flarum/forum/components/LogInModal', 'oninit', function (this: any) {
    this.magicLink = createState();
    this.magicLinkUsePassword = false;
  });

  extend('flarum/forum/components/LogInModal', 'fields', function (this: any, items: ItemList<Mithril.Children>) {
    if (this.magicLinkUsePassword) return;

    const state = this.magicLink;

    items.remove('password');
    // O "Esqueci minha senha" que o Canela põe no formulário.
    items.remove('canelaForgot');

    if (state.ref) {
      items.remove('identification');
      items.remove('remember');

      addCodeFields(items, state, {
        type: 'signin',
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

    // Outra extensão (o modo só OAuth da fof/oauth, por exemplo) pode ter tirado o botão.
    if (items.has('submit')) {
      items.setContent(
        'submit',
        submitButton(state.ref ? 'ramon-magic-link.forum.code.submit_button' : 'ramon-magic-link.forum.log_in.submit_button', this.loading)
      );
    }
  });

  // O rodapé continua o do core ou o do tema; sem senha, o "Esqueci minha
  // senha" dele some pelo CSS (classe abaixo). Com senha permitida, um link troca de modo.
  override('flarum/forum/components/LogInModal', 'footer', function (this: any, original: () => Mithril.Children) {
    const toggle = !passwordless() && (
      <p className="LogInModal-magicLinkToggle">
        <Button
          className="Button Button--text Button--link"
          onclick={() => {
            this.magicLinkUsePassword = !this.magicLinkUsePassword;
            this.magicLink.ref = null;
            this.alertAttrs = null;
          }}
        >
          {app.translator.trans(`ramon-magic-link.forum.log_in.${this.magicLinkUsePassword ? 'use_code_link' : 'use_password_link'}`)}
        </Button>
      </p>
    );

    return [toggle, original()];
  });

  override('flarum/forum/components/LogInModal', 'className', function (this: any, original: () => string) {
    return this.magicLinkUsePassword ? original() : `${original()} MagicLink--code`;
  });

  override('flarum/forum/components/LogInModal', 'onsubmit', function (this: any, original: (e: SubmitEvent) => void, e: any) {
    if (this.magicLinkUsePassword) return original(e);

    e.preventDefault();

    if (!this.magicLink.ref) {
      this.magicLinkSend(false);
      return;
    }

    this.loading = true;
    this.alertAttrs = null;

    // O código de login só entra em conta que já existe.
    verifyCode(this.magicLink, this.onerror.bind(this)).then(() => window.location.reload(), this.loaded.bind(this));
  });

  // O core seleciona a senha quando o e-mail já vem preenchido; aqui ela não existe.
  override('flarum/forum/components/LogInModal', 'onready', function (this: any, original: () => void) {
    if (this.magicLinkUsePassword) return original();

    this.$(this.magicLink.ref ? '[name=code]' : '[name=identification]').trigger('select');
  });

  extend('flarum/forum/components/LogInModal', 'oninit', function (this: any) {
    this.magicLinkSend = (resend: boolean) => {
      const state = this.magicLink;
      const identification = resend ? state.email : this.identification().trim();

      this.loading = true;
      this.alertAttrs = null;

      requestCode(state, { type: 'signin', identification, remember: this.remember() }, this.onerror.bind(this)).then(() => {
        state.email = identification;
        this.loading = false;

        if (resend) this.alertAttrs = resentAlert();

        m.redraw.sync();
        this.$('[name=code]').trigger('select');
      }, this.loaded.bind(this));
    };
  });
}
