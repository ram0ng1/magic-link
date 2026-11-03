import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';
import type RequestError from 'flarum/common/utils/RequestError';

import passwordless from '../../common/passwordless';
import CodeInput from '../components/CodeInput';

export { passwordless };

export type MagicLinkType = 'signin' | 'signup' | 'reauth';

export type VerifyResult = { action: 'login' } | { action: 'register'; token: string; email: string; username: string };

/** O estado do passo do código, guardado na instância do modal. */
export interface MagicLinkState {
  ref: string | null;
  email: string;
  code: Stream<string>;
  honeypot: Stream<string>;
  resendAt: number;
}

/** Espera depois de pedir um código, antes de poder pedir outro. */
const RESEND_DELAY = 30_000;

export function codeLength(): number {
  return app.forum.attribute<number>('magicLinkCodeLength') || 6;
}

export function createState(): MagicLinkState {
  return { ref: null, email: '', code: Stream(''), honeypot: Stream(''), resendAt: 0 };
}

export function requestCode(
  state: MagicLinkState,
  body: { type: MagicLinkType; identification?: string; username?: string; remember?: boolean },
  errorHandler: (error: RequestError) => void
): Promise<void> {
  return app
    .request<{ ref: string }>({
      method: 'POST',
      url: app.forum.attribute<string>('apiUrl') + '/magic-link',
      body: { ...body, honeypot: state.honeypot() },
      errorHandler,
    })
    .then((response) => {
      state.ref = response.ref;
      state.code('');
      state.resendAt = Date.now() + RESEND_DELAY;
    });
}

export function verifyCode(state: MagicLinkState, errorHandler: (error: RequestError) => void): Promise<VerifyResult> {
  return app.request<VerifyResult>({
    method: 'POST',
    url: app.forum.attribute<string>('baseUrl') + '/magic-link/verify',
    body: { ref: state.ref, code: state.code() },
    errorHandler,
  });
}

/** Bilhete que o servidor aceita no lugar da senha atual (trocar e-mail, apagar a conta). */
export function reauthTicket(state: MagicLinkState): string {
  return `magic-link:${state.ref}:${state.code().replace(/\D/g, '')}`;
}

/**
 * Campo que só robô preenche. Fica fora da tela, não escondido com display,
 * porque alguns robôs pulam campos invisíveis.
 */
export function honeypotField(state: MagicLinkState): Mithril.Children {
  return (
    <div className="MagicLink-honeypot" aria-hidden="true">
      <input type="text" name="magicLinkWebsite" tabindex={-1} autocomplete="off" bidi={state.honeypot} />
    </div>
  );
}

/**
 * O passo do código: título, texto, as caixas dos dígitos e os links de
 * reenviar e trocar o e-mail. As classes são as de formulário do core, então
 * o tema (Avocado, Canela) estiliza como o resto do login.
 */
export function addCodeFields(
  items: ItemList<Mithril.Children>,
  state: MagicLinkState,
  options: { type: MagicLinkType; loading: boolean; onResend: () => void; onChangeEmail?: () => void }
): void {
  const seconds = Math.ceil((state.resendAt - Date.now()) / 1000);

  // A contagem do "Reenviar código (29s)" redesenha a cada segundo até zerar.
  if (seconds > 0) setTimeout(() => m.redraw(), 1000);

  items.add('magicLinkHeading', <h3 className="MagicLink-heading">{app.translator.trans('ramon-magic-link.forum.code.heading')}</h3>, 50);

  items.add(
    'magicLinkHint',
    <p className="helpText MagicLink-hint">
      {/* Com o nome de usuário, o e-mail da conta não aparece: só quem o tem sabe qual é. */}
      {state.email.includes('@')
        ? app.translator.trans('ramon-magic-link.forum.code.sent_text', { length: codeLength(), email: <strong>{state.email}</strong> })
        : app.translator.trans('ramon-magic-link.forum.code.sent_text_username', { length: codeLength(), username: <strong>{state.email}</strong> })}
    </p>,
    40
  );

  items.add(
    'magicLinkCode',
    <div className="Form-group MagicLink-codeGroup">
      <CodeInput
        code={state.code}
        length={codeLength()}
        label={extractText(app.translator.trans('ramon-magic-link.forum.code.label'))}
        disabled={options.loading}
      />
    </div>,
    30
  );

  items.add(
    'magicLinkActions',
    <div className="Form-group MagicLink-actions">
      <Button className="Button Button--text Button--link" type="button" onclick={options.onResend} disabled={options.loading || seconds > 0}>
        {seconds > 0
          ? app.translator.trans('ramon-magic-link.forum.code.resend_countdown', { seconds })
          : app.translator.trans('ramon-magic-link.forum.code.resend_button')}
      </Button>
      {options.onChangeEmail && (
        <Button className="Button Button--text Button--link" type="button" onclick={options.onChangeEmail} disabled={options.loading}>
          {app.translator.trans('ramon-magic-link.forum.code.change_email_link')}
        </Button>
      )}
    </div>,
    -20
  );
}

export function submitButton(key: string, loading: boolean): Mithril.Children {
  return (
    <div className="Form-group">
      <Button className="Button Button--primary Button--block" type="submit" loading={loading}>
        {app.translator.trans(key)}
      </Button>
    </div>
  );
}

export function resentAlert(): { type: 'success'; content: Mithril.Children } {
  return { type: 'success', content: app.translator.trans('ramon-magic-link.forum.code.resent_message') };
}
