<?php

namespace Database\Seeders;

use App\Models\CmsForm;
use Illuminate\Database\Seeder;

/**
 * Default forms — at least one "contact" form so the `contact_form`
 * block (which defaults to form_slug=contact) renders + submits out of
 * the box on a fresh install.
 */
class CmsFormsSeeder extends Seeder
{
    public function run(): void
    {
        CmsForm::query()->updateOrCreate(
            ['slug' => 'contact'],
            [
                'name' => 'Contact',
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                    ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
                ],
                'success_message' => "Thanks! We'll get back to you within one business day.",
                'is_active' => true,
                'store_submissions' => true,
            ],
        );

        CmsForm::query()->updateOrCreate(
            ['slug' => 'newsletter'],
            [
                'name' => 'Newsletter',
                'fields' => [
                    ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ],
                'success_message' => 'Subscribed! Check your inbox to confirm.',
                'is_active' => true,
                'store_submissions' => true,
            ],
        );
    }
}
