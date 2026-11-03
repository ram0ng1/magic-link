import { extend } from 'flarum/common/extend';
import CreateUserModal from 'flarum/admin/components/CreateUserModal';
import type ItemList from 'flarum/common/utils/ItemList';

import passwordless from '../common/passwordless';

/**
 * Sem senha, o admin cria o usuário só com nome e e-mail. O core manda uma
 * senha aleatória quando o campo fica nulo, e o servidor não a guarda.
 */
export default function extendCreateUserModal() {
  extend(CreateUserModal.prototype, ['oninit', 'resetData'], function (this: any) {
    if (passwordless()) this.password(null);
  });

  extend(CreateUserModal.prototype, 'fields', function (items: ItemList<unknown>) {
    if (!passwordless()) return;

    items.remove('password');
    items.remove('useRandomPassword');
  });
}
