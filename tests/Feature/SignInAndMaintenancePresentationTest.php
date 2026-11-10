<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Setting;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SignInAndMaintenancePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('X-Analytics-Synthetic', '1');
    }

    public function test_guest_sign_in_keeps_its_plain_form_contract_and_english_branding(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk()->assertSee('<html lang="en" data-admin-appearance="light"', false)
            ->assertSee('<title>Sign In | Arabic with Abdallah Operations Console</title>', false)
            ->assertSee('alt="Arabic with Abdallah"', false)->assertSee('Operations Console')
            ->assertSee('action="'.route('admin.login.submit').'" method="POST"', false)
            ->assertSee('name="_token"', false)->assertSee('autocomplete="username"', false)
            ->assertSee('autocomplete="current-password"', false)->assertSee('name="remember"', false)
            ->assertSee(route('admin.password.request'), false)->assertDontSee('fonts.googleapis.com', false)
            ->assertDontSee('admin-sidebar', false);
        $this->assertLessThan(strpos($response->getContent(), '<link'), strpos($response->getContent(), 'data-admin-appearance-init'));
    }

    public function test_required_fields_return_accessible_errors_without_password_repopulation(): void
    {
        $response = $this->followingRedirects()->from(route('admin.login'))->post(route('admin.login.submit'), ['email' => 'operator@example.test', 'remember' => '1']);

        $response->assertOk()->assertSee('value="operator@example.test"', false)
            ->assertSee('id="password-error"', false)->assertSee('aria-describedby="password-error"', false)
            ->assertSee('aria-invalid="true"', false)->assertSee('role="alert"', false)
            ->assertSee('name="remember" value="1" checked', false)
            ->assertDontSee('name="password" value=', false);
        $this->assertGuest('web');
    }

    public function test_invalid_credentials_keep_email_and_show_an_associated_error(): void
    {
        $response = $this->followingRedirects()->from(route('admin.login'))->post(route('admin.login.submit'), ['email' => 'unknown@example.test', 'password' => 'synthetic-invalid-password']);

        $response->assertOk()->assertSee('value="unknown@example.test"', false)
            ->assertSee('id="email-error"', false)->assertSee('aria-describedby="email-error"', false)
            ->assertDontSee('synthetic-invalid-password');
        $this->assertGuest('web');
    }

    public function test_both_required_fields_have_associated_feedback_when_missing(): void
    {
        $response = $this->followingRedirects()->from(route('admin.login'))->post(route('admin.login.submit'), []);

        $response->assertOk()->assertSee('id="email-error"', false)->assertSee('id="password-error"', false)
            ->assertSeeText('The email field is required.')->assertSeeText('The password field is required.');
        $this->assertGuest('web');
    }

    public function test_session_feedback_is_escaped_and_announced(): void
    {
        $this->withSession(['error' => 'Unable <script>error</script>', 'success' => 'Signed out safely.'])
            ->get(route('admin.login'))->assertSee('role="alert"', false)->assertSee('role="status"', false)
            ->assertSee('Unable <script>error</script>')->assertDontSee('<script>error</script>', false)
            ->assertSeeText('Signed out safely.');
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith([" \n\t "])]
    #[TestWith(['null'])]
    #[TestWith(['false'])]
    #[TestWith(['[]'])]
    public function test_unusable_configured_messages_fall_back_in_real_maintenance_responses(?string $value): void
    {
        Setting::set('maintenance_mode', true);
        Setting::query()->create(['key' => 'maintenance_message', 'value' => $value]);

        $this->get('/?lang=en')->assertStatus(503)->assertViewHas('maintenanceContext', true)
            ->assertSeeText('Website maintenance')->assertSeeText('A little work behind the scenes.')
            ->assertSeeText("We're making a few improvements to Arabic with Abdallah.", false)
            ->assertDontSee('whatsapp-float', false)->assertHeaderMissing('Retry-After');
    }

    public function test_configured_message_keeps_its_text_line_breaks_and_translation_lookup(): void
    {
        Setting::set('maintenance_mode', true);
        $message = "Returning tomorrow <script>alert(1)</script>\nSecond line.";
        Setting::set('maintenance_message', $message);

        $this->get('/?lang=en')->assertStatus(503)->assertSee($message)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame($message, Setting::get('maintenance_message'));
        Setting::set('maintenance_message', 'Please try again later. Thank you for your patience.');
        $this->get('/fr')->assertStatus(503)->assertSeeText('Veuillez réessayer plus tard. Merci de votre patience.');
    }

    public function test_failed_optional_message_lookup_does_not_break_the_maintenance_response(): void
    {
        Setting::set('maintenance_mode', true);
        DB::connection()->beforeExecuting(function (string $query, array $bindings): void {
            if (in_array('maintenance_message', $bindings, true)) {
                throw new RuntimeException('Synthetic optional message lookup failure.');
            }
        });

        $this->get('/?lang=en')->assertStatus(503)->assertSeeText('A little work behind the scenes.')
            ->assertSeeText("We're making a few improvements to Arabic with Abdallah.", false)
            ->assertDontSee('Synthetic optional message lookup failure.');
    }

    #[TestWith(['en', 'The website is temporarily unavailable.'])]
    #[TestWith(['fr', 'Le site est temporairement indisponible.'])]
    #[TestWith(['de', 'Die Website ist vorübergehend nicht verfügbar.'])]
    public function test_framework_503_preserves_headers_and_renders_without_database_or_vite(string $locale, string $heading): void
    {
        config(['app.debug' => false]);
        app()->setLocale($locale);
        app(Vite::class)->useManifestFilename('nonexistent-error-rendering-test.json');
        $forbidQueries = true;
        DB::connection()->beforeExecuting(function () use (&$forbidQueries): void {
            if ($forbidQueries) {
                throw new RuntimeException('Error views must not query the database.');
            }
        });

        try {
            $response = app(ExceptionHandler::class)->render(Request::create('/'.$locale), new HttpException(503, 'Private exception details', null, ['Retry-After' => '120', 'X-Existing-Response' => 'preserved']));
            $compatibility = view('errors.maintenance')->render();
            $confirmed = view('errors.503', ['maintenanceContext' => true])->render();
        } finally {
            $forbidQueries = false;
        }

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('120', $response->headers->get('Retry-After'));
        $this->assertSame('preserved', $response->headers->get('X-Existing-Response'));
        $this->assertStringContainsString($heading, $response->getContent());
        $this->assertStringContainsString('lang="'.$locale.'"', $response->getContent());
        $this->assertStringContainsString($heading, $compatibility);
        $this->assertStringContainsString(__('A little work behind the scenes.'), $confirmed);
        foreach (['Private exception details', 'scheduled maintenance', 'resources/js', '/build/', '@vite', 'fonts.googleapis.com', '<script'] as $unwanted) {
            $this->assertStringNotContainsString($unwanted, $response->getContent());
        }
    }
}
