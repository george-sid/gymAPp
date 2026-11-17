<?php

namespace Tests\Feature\Livewire\Clients;

use App\Enums\PaymentMethod;
use App\Livewire\Clients\Packages;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackagesTest extends TestCase
{
    use RefreshDatabase;

    private User $trainer;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = User::factory()->admin()->create();
        $this->client = User::factory()->client($this->trainer)->create();
    }

    public function test_assigns_package_with_a_discounted_price(): void
    {
        $package = Package::factory()->create(['price' => 300]);

        Livewire::actingAs($this->trainer)
            ->test(Packages::class, ['client' => $this->client])
            ->call('openAssign')
            ->assertSet('packageId', (string) $package->id)
            ->assertSet('price', '300.00')
            ->set('price', '270')
            ->call('assign')
            ->assertHasNoErrors()
            ->assertSee($package->name);

        $this->assertSame('270.00', ClientPackage::query()->where('client_id', $this->client->id)->sole()->price);
    }

    public function test_records_payment_against_the_package_with_balance_due(): void
    {
        $clientPackage = ClientPackage::factory()->for($this->client, 'client')->create(['price' => 300]);
        Payment::factory()->for($this->client, 'client')->create(['client_package_id' => $clientPackage->id, 'amount' => 100]);

        $component = Livewire::actingAs($this->trainer)
            ->test(Packages::class, ['client' => $this->client])
            ->call('openPayment')
            ->assertSet('paymentPackageId', (string) $clientPackage->id)
            ->assertSet('amount', '200')
            ->set('method', PaymentMethod::Iris->value)
            ->set('receiptNumber', 'Α-123')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $payment = Payment::query()->latest('id')->first();
        $this->assertSame('200.00', $payment->amount);
        $this->assertSame(PaymentMethod::Iris, $payment->method);
        $this->assertSame('Α-123', $payment->receipt_number);
        $this->assertSame($this->trainer->id, $payment->recorded_by);
        $this->assertSame(0.0, $component->instance()->account['amount_due']);
    }

    public function test_rejects_zero_amount_future_date_and_foreign_package(): void
    {
        $foreignPackage = ClientPackage::factory()->create();

        Livewire::actingAs($this->trainer)
            ->test(Packages::class, ['client' => $this->client])
            ->call('openPayment')
            ->set('amount', '0')
            ->set('paidOn', now()->addDays(2)->toDateString())
            ->set('paymentPackageId', (string) $foreignPackage->id)
            ->call('recordPayment')
            ->assertHasErrors(['amount', 'paidOn', 'paymentPackageId']);
    }

    public function test_deletes_a_wrong_payment(): void
    {
        $payment = Payment::factory()->for($this->client, 'client')->create();

        Livewire::actingAs($this->trainer)
            ->test(Packages::class, ['client' => $this->client])
            ->call('deletePayment', $payment->id);

        $this->assertModelMissing($payment);
    }

    public function test_cannot_delete_another_clients_payment(): void
    {
        $payment = Payment::factory()->create();

        Livewire::actingAs($this->trainer)
            ->test(Packages::class, ['client' => $this->client])
            ->call('deletePayment', $payment->id)
            ->assertNotFound();
    }

    public function test_tab_renders_inside_the_client_card(): void
    {
        ClientPackage::factory()->for($this->client, 'client')->create(['name' => 'Πακέτο Δοκιμής']);

        $this->actingAs($this->trainer)
            ->get(route('clients.show', ['client' => $this->client, 'tab' => 'packages']))
            ->assertOk()
            ->assertSee('Πακέτο Δοκιμής')
            ->assertSee('0/10 συνεδρίες');
    }

    public function test_another_trainer_cannot_open_the_tab(): void
    {
        Livewire::actingAs(User::factory()->trainer()->create())
            ->test(Packages::class, ['client' => $this->client])
            ->assertForbidden();
    }
}
