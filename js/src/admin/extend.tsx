import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

export default [
  new Extend.Admin()
    .setting(
      () => ({
        setting: 'ramon-magic-link.allow_password',
        type: 'boolean',
        label: app.translator.trans('ramon-magic-link.admin.settings.allow_password_label'),
        help: app.translator.trans('ramon-magic-link.admin.settings.allow_password_help'),
      }),
      20
    )
    .setting(
      () => ({
        setting: 'ramon-magic-link.token_lifetime',
        type: 'number',
        min: 5,
        max: 1440,
        label: app.translator.trans('ramon-magic-link.admin.settings.token_lifetime_label'),
        help: app.translator.trans('ramon-magic-link.admin.settings.token_lifetime_help'),
      }),
      10
    ),
];
