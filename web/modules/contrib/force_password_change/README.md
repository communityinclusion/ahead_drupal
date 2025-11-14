# FORCE PASSWORD CHANGE

This module allows administrators to force users, by role, individual user, or newly created user, to change 
their password on their next page load or login, and/or expire their passwords after a period of time.

## FEATURES

- Ability to force all users in a role to change their password
- Ability to force individual users to reset their password from their profile edit page (user/[UID]/edit)
- Ability to set an expiry on passwords so that if users haven't changed their password within that time period, 
  they will be required to do so
- Ability to force all new users to change their password on first-time login (site-wide setting for all new     
  users)
- Ability for admins to force individual users to change their password on first time login when creating a new 
  user. (Note: If the global setting forcing all new users to reset their password is enabled on the module 
- settings page, this checkbox will not appear as it is redundant)
- Listing of stats on the user edit page (user/[UID]/edit) showing:
  - Whether the user has a pending forced password change
  - When the user last had their password forced to be changed
  - When the user last changed their password
- Status page for each role showing:
  - Password change details by user
  - The last time at which the role was forced to change the password
  - A form to force the password change for all users in that role

## IF YOUR SITE BECOMES INACCESSIBLE

If you somehow get locked out of your site, you can temporarily disable
the module functionality by editing settings.php and adding the following line:

$config['force_password_change.settings']['enabled'] = FALSE;

Do what you need to do, then remove the above line to
re-enable the module functionality

## MAINATINERS

- Jay Friendly (Jaypan) - <https://www.drupal.org/u/jaypan>
- Larisse Amorim (larisse) - <https://www.drupal.org/u/larisse>
- Dan Reinders (SpartyDan) - <https://www.drupal.org/u/spartydan>
