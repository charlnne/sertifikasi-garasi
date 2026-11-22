<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Renter applicant verification by admins: approve (approved) or reject (rejected). Decisions are final.
 *
 * @author Charlene
 *
 * @version 1.0
 */
class RenterVerificationService
{
    public const PER_PAGE = 10;

    public function paginate(UserStatus $status = UserStatus::Pending): LengthAwarePaginator
    {
        return User::query()
            ->where('role', UserRole::Renter)
            ->where('status', $status)
            ->orderBy($status === UserStatus::Pending ? 'created_at' : 'updated_at', $status === UserStatus::Pending ? 'asc' : 'desc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function countPending(): int
    {
        return User::where('role', UserRole::Renter)->where('status', UserStatus::Pending)->count();
    }

    /**
     * Renter count per status for the tab labels.
     *
     * @return array<string, int> Keyed by UserStatus value.
     */
    public function countByStatus(): array
    {
        $counts = User::where('role', UserRole::Renter)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(UserStatus::cases())
            ->mapWithKeys(fn (UserStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /**
     * Approve an applicant so they can start renting.
     *
     * @throws DomainException If the user is not a renter or has already been verified.
     */
    public function approve(User $renter): User
    {
        return $this->changeStatus($renter, UserStatus::Approved);
    }

    /**
     * Reject an applicant so they can no longer log in.
     *
     * @throws DomainException If the user is not a renter or has already been verified.
     */
    public function reject(User $renter): User
    {
        return $this->changeStatus($renter, UserStatus::Rejected);
    }

    private function changeStatus(User $renter, UserStatus $status): User
    {
        return DB::transaction(function () use ($renter, $status) {
            // Locked so two admins cannot process the same applicant at the same time.
            $renter = User::whereKey($renter->id)->lockForUpdate()->firstOrFail();

            if (! $renter->isRenter()) {
                throw new DomainException('Only renter accounts can be verified.');
            }

            if (! $renter->isPending()) {
                throw new DomainException("Applicant {$renter->name} has already been verified.");
            }

            $renter->update(['status' => $status]);

            return $renter;
        });
    }
}
