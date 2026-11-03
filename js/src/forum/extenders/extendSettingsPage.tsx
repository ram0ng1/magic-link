import { extend } from 'flarum/common/extend';
import type Mithril from 'mithril';
import type ItemList from 'flarum/common/utils/ItemList';

import { passwordless } from '../utils/magicLink';

export default function extendSettingsPage() {
  // SettingsPage é carregada sob demanda no 2.x.
  extend('flarum/forum/components/SettingsPage', 'accountItems', function (items: ItemList<Mithril.Children>) {
    if (passwordless()) items.remove('changePassword');
  });

  // O Canela repete o "Alterar senha" na aba Segurança.
  extend('flarum/forum/components/UserSecurityPage', 'settingsItems', function (items: ItemList<Mithril.Children>) {
    if (passwordless()) items.remove('canelaPassword');
  });
}
