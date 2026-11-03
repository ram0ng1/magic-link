import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';
import type RequestError from 'flarum/common/utils/RequestError';

import passwordless from '../../common/passwordless';

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

      // Redesenha quando o "Enviar um novo código" volta a valer.
      setTimeout(() => m.redraw(), RESEND_DELAY + 50);
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
 * O passo do código, com as classes de formulário do core: o tema (Avocado,
 * Canela) estiliza os campos como os do login.
 */
export function addCodeFields(
  items: ItemList<Mithril.Children>,
  state: MagicLinkState,
  options: { type: MagicLinkType; loading: boolean; onResend: () => void; onChangeEmail?: () => void }
): void {
  const label = extractText(app.translator.trans('ramon-magic-link.forum.code.label'));
  const canResend = Date.now() >= state.resendAt;

  items.add(
    'magicLinkHint',
    <p className="helpText MagicLink-hint">
      {app.translator.trans(`ramon-magic-link.forum.code.sent_${options.type}`, { email: <strong>{state.email}</strong> })}
    </p>,
    40
  );

  items.add(
    'magicLinkCode',
    <div className="Form-group MagicLink-codeGroup">
      <label className="label" for="magicLinkCode">
        {label}
      </label>
      <input
        id="magicLinkCode"
        className="FormControl MagicLink-code"
        name="code"
        type="text"
        inputmode="numeric"
        autocomplete="one-time-code"
        pattern="[0-9]*"
        maxlength={codeLength()}
        placeholder={'0'.repeat(codeLength())}
        aria-label={label}
        bidi={state.code}
        disabled={options.loading}
      />
    </div>,
    30
  );

  items.add(
    'magicLinkActions',
    <div className="Form-group MagicLink-actions">
      <Button className="Button Button--text Button--link" type="button" onclick={options.onResend} disabled={options.loading || !canResend}>
        {app.translator.trans('ramon-magic-link.forum.code.resend_button')}
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
