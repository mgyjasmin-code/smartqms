# Field-Level Form Errors Without URL Error Messages

## Summary
- Replace `?error=...` redirects with session-based flash form feedback across SmartQMS forms.
- Show user-caused validation errors beside the exact input/select that needs correction.
- Preserve safe submitted values after errors, especially the login email; never repopulate passwords or OTP codes.
- Keep non-field workflow/system failures as page alerts, but store them in session flash, not the URL.

## Key Changes
- Add shared helpers in `config/helpers.php`:
  - `flashFormFeedback($formKey, $fieldErrors, $oldInput = [], $formError = '')`
  - `consumeFormFeedback($formKey)`
  - `redirectWithFormFeedback($path, $formKey, ...)`
- Session shape:
  - `$_SESSION['form_feedback'][$formKey]['field_errors']`
  - `$_SESSION['form_feedback'][$formKey]['old']`
  - `$_SESSION['form_feedback'][$formKey]['form_error']`
- Keep `msg` success query params for existing success flows; stop generating and rendering `error` query params.

## Implementation Changes
- Login:
  - Unknown email: show `No account was found with this email address.` under Email Address.
  - Correct email + wrong password: show `Incorrect password. Please try again.` under Password.
  - Preserve `login_id`; keep password blank.
  - Inactive account: show field-level email/account message.
- Auth/account forms:
  - Update registration, OTP verification, forgot-password OTP, and password reset handlers to return field-specific errors through session flash.
  - Duplicate registration email appears under Email.
  - Wrong/expired OTP appears under OTP Code.
  - Password length/mismatch appears under the relevant password fields.
- Other forms:
  - Client queue form: missing/inactive service shows under Health Service; priority-only restriction shows under Client Classification.
  - Admin add-staff form: first name, last name, phone, email, and password each get specific errors; duplicate phone/email maps to the matching field.
  - Admin service-window form: blank window name and invalid status map to their fields.
  - Feedback AJAX form: return optional `field_errors` JSON and render rating errors under Rating; keep ticket-not-found/duplicate feedback as form-level alerts.
- UI/JS:
  - Extend `assets/js/main.js` validation from only `.js-auth-form` to reusable validated forms.
  - Validate `[data-validate]` and required fields, including `.form-control` and `.form-select`.
  - Add/keep `.field-error` blocks with `aria-live`, `aria-invalid`, and focus first invalid field after submit.
  - Ensure server-rendered errors clear when the user edits the field.

## Test Plan
- Login with correct email + wrong password: email remains, password is blank, password field shows the error, and URL has no `error=`.
- Login with unknown email: email field shows account-not-found error.
- Register with missing fields, invalid email, short password, and duplicate email.
- Verify OTP with empty, wrong, and expired OTP.
- Forgot password with unknown email and mismatched new passwords.
- Join queue without a service and with a priority-only service as a regular client.
- Add staff with invalid phone/email, short password, duplicate phone, and duplicate email.
- Create window with blank name.
- Submit feedback with invalid/missing rating.
- Run `php -l` on modified PHP files and manually verify success `msg` flows still work.

## Assumptions
- “Entire system” means all SmartQMS forms that accept user input; button-only actions and workflow/server failures may still use form/page alerts.
- Passwords and OTP codes must not be preserved in session, HTML, or URL.
- The target app is `C:\xampp\htdocs\smartqms`; implementation will need write access there because the current writable workspace is `C:\Users\User\vite-react`.
