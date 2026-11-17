<?php

namespace App\Livewire\Clients;

use App\Actions\Packages\AssignPackage;
use App\Actions\Packages\GetClientAccount;
use App\Actions\Packages\RecordPayment;
use App\Enums\PaymentMethod;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Packages & payments tab of the client card.
 */
class Packages extends Component
{
    #[Locked]
    public int $clientId;

    public bool $showAssign = false;

    public ?string $packageId = null;

    public string $startsOn = '';

    public string $price = '';

    public string $packageNotes = '';

    public bool $showPayment = false;

    public string $amount = '';

    public string $method = 'cash';

    public string $paidOn = '';

    public ?string $paymentPackageId = null;

    public string $receiptNumber = '';

    public string $paymentNotes = '';

    public function mount(User $client): void
    {
        $this->authorize('view', $client);

        $this->clientId = $client->id;
    }

    #[Computed]
    public function client(): User
    {
        return User::query()->findOrFail($this->clientId);
    }

    /**
     * @return array{available_sessions: int, upcoming_bookings: int, free_sessions: int, owed_sessions: int, amount_due: float}
     */
    #[Computed]
    public function account(): array
    {
        return app(GetClientAccount::class)->handle($this->client);
    }

    /**
     * @return Collection<int, ClientPackage>
     */
    #[Computed]
    public function clientPackages(): Collection
    {
        return ClientPackage::query()
            ->where('client_id', $this->clientId)
            ->withUsage()
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function payments(): Collection
    {
        return Payment::query()
            ->where('client_id', $this->clientId)
            ->with('clientPackage')
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, Package>
     */
    #[Computed]
    public function packageTemplates(): Collection
    {
        return Package::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function openAssign(): void
    {
        $this->reset(['packageId', 'price', 'packageNotes']);
        $this->resetValidation();
        $this->startsOn = LocalTime::now()->toDateString();
        $this->packageId = $this->packageTemplates->first()?->id ? (string) $this->packageTemplates->first()->id : null;
        $this->price = (string) $this->packageTemplates->first()?->price;
        $this->showAssign = true;
    }

    public function updatedPackageId(): void
    {
        $this->price = (string) $this->packageTemplates->firstWhere('id', $this->packageId)?->price;
    }

    public function assign(AssignPackage $assignPackage): void
    {
        $this->authorize('update', $this->client);

        $this->validate([
            'packageId' => ['required', Rule::in($this->packageTemplates->modelKeys())],
            'startsOn' => ['required', 'date'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'packageNotes' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['packageId' => 'πακέτο', 'startsOn' => 'έναρξη', 'price' => 'τιμή', 'packageNotes' => 'σημειώσεις']);

        $assignPackage->handle($this->client, Package::query()->findOrFail($this->packageId), $this->startsOn, $this->price, $this->packageNotes ?: null);

        $this->showAssign = false;
        $this->refreshAccount();
        $this->dispatch('notify', message: 'Το πακέτο προστέθηκε.');
    }

    public function openPayment(?int $clientPackageId = null): void
    {
        $this->reset(['amount', 'method', 'receiptNumber', 'paymentNotes']);
        $this->resetValidation();
        $this->paidOn = LocalTime::now()->toDateString();

        $package = $clientPackageId
            ? $this->clientPackages->firstWhere('id', $clientPackageId)
            : $this->clientPackages->first(fn (ClientPackage $package) => $package->amountDue() > 0);

        $this->paymentPackageId = $package ? (string) $package->id : null;
        $this->amount = $package && $package->amountDue() > 0 ? (string) $package->amountDue() : '';
        $this->showPayment = true;
    }

    public function recordPayment(RecordPayment $recordPayment): void
    {
        $this->authorize('update', $this->client);

        $this->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paidOn' => ['required', 'date', 'before_or_equal:today'],
            'paymentPackageId' => ['nullable', Rule::in($this->clientPackages->modelKeys())],
            'receiptNumber' => ['nullable', 'string', 'max:50'],
            'paymentNotes' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'amount' => 'ποσό',
            'method' => 'τρόπος πληρωμής',
            'paidOn' => 'ημερομηνία',
            'paymentPackageId' => 'πακέτο',
            'receiptNumber' => 'αριθμός απόδειξης',
            'paymentNotes' => 'σημειώσεις',
        ]);

        $recordPayment->handle($this->client, [
            'amount' => $this->amount,
            'method' => $this->method,
            'paid_on' => $this->paidOn,
            'client_package_id' => $this->paymentPackageId ? (int) $this->paymentPackageId : null,
            'receipt_number' => $this->receiptNumber ?: null,
            'notes' => $this->paymentNotes ?: null,
        ], auth()->user());

        $this->showPayment = false;
        $this->refreshAccount();
        $this->dispatch('notify', message: 'Η πληρωμή καταγράφηκε.');
    }

    public function deletePayment(int $paymentId): void
    {
        $this->authorize('update', $this->client);

        Payment::query()->where('client_id', $this->clientId)->findOrFail($paymentId)->delete();

        $this->refreshAccount();
    }

    public function closeModals(): void
    {
        $this->showAssign = false;
        $this->showPayment = false;
    }

    public function render(): View
    {
        return view('livewire.clients.packages', [
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    private function refreshAccount(): void
    {
        unset($this->account, $this->clientPackages, $this->payments);
    }
}
