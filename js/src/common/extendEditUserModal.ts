import { extend } from 'flarum/common/extend';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';

import passwordless from './passwordless';

/** Sem senha, o "Definir senha" do editar usuário (forum e admin) só seria apagado no servidor. */
export default function extendEditUserModal() {
  extend('flarum/common/components/EditUserModal', 'fields', function (items: ItemList<Mithril.Children>) {
    if (passwordless()) items.remove('password');
  });
}
