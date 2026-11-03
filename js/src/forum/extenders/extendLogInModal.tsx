import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';

import { addCodeFields, createState, honeypotField, passwordless, requestCode, resentAlert, submitButton, verifyCode } from '../utils/magicLink';

/**
 * O login por e-mail dentro do LogInModal do core (o Avocado envolve o
 * content() dele e o Canela o desenha na página /login): só os campos e o
 * envio mudam.
 *
 * - Sem senha: e-mail ou nome de usuário → código.
 * - Com a senha permitida no admin: o formulário de senha de sempre, com um
 *   link embaixo do e-mail que manda o código.
 *
 * LogInModal é carregado sob demanda no 2.x, então é estendido pelo caminho do módulo.
 */
export default function extendLogInModal() {
  extend('flarum/forum/components/LogInModal', 'oninit', function (this: any) {
    this.magicLink = createState();

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
        this.$('[name=code]').trigger('focus');
      }, this.loaded.bind(this));
    };
  });

  extend('flarum/forum/components/LogInModal', 'fields', function (this: any, items: ItemList<Mithril.Children>) {
    const state = this.magicLink;

    if (state.ref) {
      // O "Esqueci minha senha" que o Canela põe no formulário.
      ['identification', 'password', 'remember', 'canelaForgot'].forEach((key) => items.remove(key));

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

      if (passwordless()) {
        items.remove('password');
        items.remove('canelaForgot');
      } else if (items.has('identification')) {
        // Entre o e-mail e a senha (prioridades 30 e 20 no core).
        items.add(
          'magicLinkUseCode',
          <div className="Form-group MagicLink-useCode">
            <Button className="Button Button--text Button--link" type="button" onclick={() => this.magicLinkSend(false)} disabled={this.loading}>
              {app.translator.trans('ramon-magic-link.forum.log_in.use_code_link')}
            </Button>
          </div>,
          25
        );
      }
    }

    // Outra extensão (o modo só OAuth da fof/oauth, por exemplo) pode ter tirado o botão.
    if (items.has('submit') && (state.ref || passwordless())) {
      items.setContent(
        'submit',
        submitButton(state.ref ? 'ramon-magic-link.forum.code.submit_button' : 'ramon-magic-link.forum.log_in.submit_button', this.loading)
      );
    }
  });

  // Sem senha, o "Esqueci minha senha" do rodapé some pelo CSS. A classe não
  // pode mudar com o modal aberto: o Mithril tiraria o `in` da animação e o
  // modal ficaria transparente (no passo do código o CSS usa :has()).
  override('flarum/forum/components/LogInModal', 'className', function (this: any, original: () => string) {
    return passwordless() ? `${original()} MagicLink--code` : original();
  });

  override('flarum/forum/components/LogInModal', 'onsubmit', function (this: any, original: (e: SubmitEvent) => void, e: any) {
    if (!this.magicLink.ref && !passwordless()) return original(e);

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

  // O core seleciona a senha quando o e-mail já vem preenchido; sem senha ela não existe.
  override('flarum/forum/components/LogInModal', 'onready', function (this: any, original: () => void) {
    if (this.magicLink.ref) return this.$('[name=code]').trigger('focus');
    if (!passwordless()) return original();

    this.$('[name=identification]').trigger('select');
  });
}
