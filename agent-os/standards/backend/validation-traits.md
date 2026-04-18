# Validation Traits (app/Concerns)

Reusable validation rules live in trait methods under `app/Concerns/`. FormRequests and Fortify Actions both `use` them so rules stay canonical across registration, profile update, and password reset.

## Use
```php
// app/Concerns/PasswordValidationRules.php
trait PasswordValidationRules {
    protected function passwordRules(): array {
        return ['required', 'string', Password::default(), 'confirmed'];
    }
}

// FormRequest
class PasswordUpdateRequest extends FormRequest {
    use PasswordValidationRules;
    public function rules(): array {
        return ['password' => $this->passwordRules()];
    }
}

// Fortify Action
class CreateNewUser implements CreatesNewUsers {
    use PasswordValidationRules, ProfileValidationRules;
    public function create(array $input): User {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();
    }
}
```

## Rules
- Promote to a trait when the same rule appears in **2+ places** — single-use rules stay inline
- Method naming: `<field>Rules()` returns rules for one field (e.g. `passwordRules`, `emailRules`)
- Group methods compose per-field methods (e.g. `profileRules()` returns `['name' => $this->nameRules(), 'email' => $this->emailRules($id)]`)
- Pass context (like ignored user id for unique checks) as a method arg, not via trait state
- Trait file = `app/Concerns/<Domain>ValidationRules.php`
