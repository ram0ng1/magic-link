import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';

import { passwordless } from '../utils/magicLink';

/**
 * O modal (ou a página /forgot-password do Canela) ainda pode ser aberto por
 * um link antigo ou por outra extensão: sem senha, ele explica e leva ao login.
 */
export default function extendForgotPasswordModal() {
  extend('flarum/forum/components/ForgotPasswordModal', 'fields', function (this: any, items: ItemList<Mithril.Children>) {
    if (!passwordless()) return;

    Object.keys(items.toObject()).forEach((key) => items.remove(key));

    items.add('magicLinkHint', <p className="helpText">{app.translator.trans('ramon-magic-link.forum.forgot_password.passwordless_text')}</p>, 20);
    items.add(
      'magicLinkLogIn',
      <div className="Form-group">
        <Button
          className="Button Button--primary Button--block"
          type="button"
          onclick={() => app.modal.show(() => import('flarum/forum/components/LogInModal'))}
        >
          {app.translator.trans('ramon-magic-link.forum.forgot_password.log_in_button')}
        </Button>
      </div>,
      10
    );
  });

  override('flarum/forum/components/ForgotPasswordModal', 'className', function (this: any, original: () => string) {
    return passwordless() ? `${original()} MagicLink--passwordless` : original();
  });

  override('flarum/forum/components/ForgotPasswordModal', 'onsubmit', function (this: any, original: (e: SubmitEvent) => void, e: any) {
    if (!passwordless()) return original(e);

    e.preventDefault();
  });
}
