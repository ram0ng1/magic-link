import Component, { type ComponentAttrs } from 'flarum/common/Component';
import classList from 'flarum/common/utils/classList';
import type Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';

export interface ICodeInputAttrs extends ComponentAttrs {
  code: Stream<string>;
  length: number;
  label: string;
  disabled?: boolean;
}

/**
 * O código em caixas, uma por dígito. Por baixo é um input só, transparente por
 * cima das caixas: colar o código, o preenchimento automático do celular
 * (`one-time-code`) e o backspace funcionam como num campo comum.
 */
export default class CodeInput extends Component<ICodeInputAttrs> {
  focused = false;

  view() {
    const { code, length, label, disabled } = this.attrs;
    const value = code();
    const active = this.focused ? Math.min(value.length, length - 1) : -1;

    return (
      <div className={classList('MagicLink-otp', { disabled })}>
        <input
          id="magicLinkCode"
          className="MagicLink-otpInput"
          name="code"
          type="text"
          inputmode="numeric"
          autocomplete="one-time-code"
          pattern="[0-9]*"
          maxlength={length}
          aria-label={label}
          value={value}
          disabled={disabled}
          oninput={(e: InputEvent) => {
            const target = e.target as HTMLInputElement;
            const digits = target.value.replace(/\D/g, '').slice(0, length);

            target.value = digits;
            code(digits);
          }}
          onfocus={() => (this.focused = true)}
          onblur={() => (this.focused = false)}
          // As caixas não têm cursor: ele fica sempre no fim do que já foi digitado.
          onkeyup={(e: KeyboardEvent) => this.caretToEnd(e.target as HTMLInputElement)}
          onclick={(e: MouseEvent) => this.caretToEnd(e.target as HTMLInputElement)}
        />
        <div className="MagicLink-otpBoxes" aria-hidden="true">
          {Array.from({ length }, (_, i) => (
            <span className={classList('MagicLink-otpBox', { 'is-filled': i < value.length, 'is-active': i === active })}>{value[i] || '-'}</span>
          ))}
        </div>
      </div>
    );
  }

  caretToEnd(input: HTMLInputElement) {
    const end = input.value.length;

    if (input.selectionStart !== end) input.setSelectionRange(end, end);
  }

  onupdate(vnode: Mithril.VnodeDOM<ICodeInputAttrs, this>) {
    super.onupdate(vnode);

    // O valor pode mudar por fora (novo código pedido, campo limpo).
    const input = this.element.querySelector('input');

    if (input && input.value !== this.attrs.code()) input.value = this.attrs.code();
  }
}
