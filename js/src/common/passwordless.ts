import app from 'flarum/common/app';

/** No forum e no admin: o atributo vem do ForumResource. */
export default function passwordless(): boolean {
  return !!app.forum?.attribute<boolean>('magicLinkPasswordless');
}
