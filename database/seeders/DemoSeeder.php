<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderActivity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::updateOrCreate(
            ['slug' => 'northstar-operations'],
            ['name' => 'Northstar Operations', 'timezone' => 'America/Toronto']
        );

        $people = [
            ['name' => 'Maya Chen', 'email' => 'maya@opsflow.demo'],
            ['name' => 'Alex Morgan', 'email' => 'alex@opsflow.demo'],
            ['name' => 'Jordan Lee', 'email' => 'jordan@opsflow.demo'],
        ];

        $users = collect($people)->mapWithKeys(function (array $person) use ($organization) {
            $user = User::updateOrCreate(
                ['email' => $person['email']],
                [
                    'organization_id' => $organization->id,
                    'name' => $person['name'],
                    'password' => Hash::make('password'),
                ]
            );

            return [$person['name'] => $user];
        });

        $clientRows = [
            ['name' => 'Avery Patel', 'company_name' => 'Harbour Dental Group', 'email' => 'avery@harbourdental.demo', 'phone' => '416-555-0114', 'city' => 'Toronto', 'province' => 'Ontario'],
            ['name' => 'Liam Brooks', 'company_name' => 'Maple & Co. Retail', 'email' => 'liam@mapleco.demo', 'phone' => '905-555-0162', 'city' => 'Mississauga', 'province' => 'Ontario'],
            ['name' => 'Sofia Rivera', 'company_name' => 'Northstar Fitness', 'email' => 'sofia@northstarfitness.demo', 'phone' => '647-555-0199', 'city' => 'Toronto', 'province' => 'Ontario'],
            ['name' => 'Noah Bennett', 'company_name' => 'Beacon Property Group', 'email' => 'noah@beaconproperty.demo', 'phone' => '289-555-0138', 'city' => 'Oakville', 'province' => 'Ontario'],
            ['name' => 'Priya Shah', 'company_name' => 'Juniper Wellness', 'email' => 'priya@juniperwellness.demo', 'phone' => '416-555-0171', 'city' => 'Toronto', 'province' => 'Ontario'],
            ['name' => 'Ethan Kim', 'company_name' => 'Cedar Grove Learning', 'email' => 'ethan@cedargrove.demo', 'phone' => '905-555-0186', 'city' => 'Markham', 'province' => 'Ontario'],
        ];

        $clients = collect($clientRows)->mapWithKeys(function (array $row) use ($organization) {
            $client = Client::updateOrCreate(
                ['organization_id' => $organization->id, 'company_name' => $row['company_name']],
                array_merge($row, ['organization_id' => $organization->id])
            );

            return [$row['company_name'] => $client];
        });

        $today = now()->startOfDay();

        $orders = [
            ['WO-1001', 'Replace reception display', 'Harbour Dental Group', 'Alex Morgan', 'in_progress', 'urgent', -2, 'Reception display is intermittently blank. Replacement unit has been ordered.'],
            ['WO-1002', 'Prepare new store opening checklist', 'Maple & Co. Retail', 'Maya Chen', 'queued', 'high', 2, 'Coordinate equipment, signage, access, and opening-day readiness.'],
            ['WO-1003', 'Investigate HVAC complaint', 'Beacon Property Group', 'Jordan Lee', 'blocked', 'high', -1, 'Tenant reported inconsistent heating on the third floor. Waiting on building access approval.'],
            ['WO-1004', 'Set up front desk tablet', 'Juniper Wellness', 'Alex Morgan', 'queued', 'normal', 4, 'Configure the tablet, kiosk mode, and staff login.'],
            ['WO-1005', 'Replace classroom projector cable', 'Cedar Grove Learning', 'Jordan Lee', 'completed', 'normal', -5, 'Replace damaged HDMI run and test projector input.', -2],
            ['WO-1006', 'Update membership kiosk signage', 'Northstar Fitness', 'Maya Chen', 'completed', 'low', -8, 'Swap signage and verify QR code links.', -4],
            ['WO-1007', 'Repair locker room access reader', 'Northstar Fitness', 'Alex Morgan', 'in_progress', 'urgent', 0, 'Access reader is failing intermittently during peak hours.'],
            ['WO-1008', 'Review recurring printer issue', 'Harbour Dental Group', 'Jordan Lee', 'draft', 'normal', 6, 'Front office printer is jamming several times per week.'],
            ['WO-1009', 'Install lobby directory update', 'Beacon Property Group', 'Maya Chen', 'queued', 'normal', 3, 'Install revised tenant directory graphics in the main lobby.'],
            ['WO-1010', 'Configure staff onboarding laptops', 'Cedar Grove Learning', 'Alex Morgan', 'in_progress', 'high', 1, 'Prepare three laptops for incoming staff and confirm required applications.'],
            ['WO-1011', 'Check treatment room monitor mount', 'Juniper Wellness', 'Jordan Lee', 'completed', 'normal', -3, 'Tighten monitor arm and verify cable routing.', -1],
            ['WO-1012', 'Remove retired POS terminal', 'Maple & Co. Retail', null, 'cancelled', 'low', 5, 'Removal no longer required after store layout changed.'],
        ];

        foreach ($orders as $row) {
            [$number, $title, $company, $assignee, $status, $priority, $dueOffset, $description] = $row;
            $completedOffset = $row[8] ?? null;

            $order = WorkOrder::updateOrCreate(
                ['organization_id' => $organization->id, 'order_number' => $number],
                [
                    'organization_id' => $organization->id,
                    'client_id' => $clients[$company]->id,
                    'assignee_id' => $assignee ? $users[$assignee]->id : null,
                    'order_number' => $number,
                    'title' => $title,
                    'description' => $description,
                    'internal_notes' => null,
                    'status' => $status,
                    'priority' => $priority,
                    'due_date' => $today->copy()->addDays($dueOffset),
                    'completed_at' => $completedOffset !== null ? $today->copy()->addDays($completedOffset)->setTime(14, 30) : null,
                ]
            );

            WorkOrderActivity::where('work_order_id', $order->id)->delete();

            WorkOrderActivity::create([
                'work_order_id' => $order->id,
                'user_id' => $users['Maya Chen']->id,
                'type' => 'created',
                'changes' => ['status' => ['from' => null, 'to' => 'draft']],
                'note' => 'Work order created.',
            ]);

            if ($status !== 'draft') {
                WorkOrderActivity::create([
                    'work_order_id' => $order->id,
                    'user_id' => ($assignee && isset($users[$assignee])) ? $users[$assignee]->id : $users['Maya Chen']->id,
                    'type' => 'updated',
                    'changes' => ['status' => ['from' => 'draft', 'to' => $status]],
                    'note' => 'Status updated to '.str_replace('_', ' ', $status).'.',
                ]);
            }
        }

        $this->command?->info('OpsFlow demo data seeded.');
        $this->command?->line('Login: maya@opsflow.demo / password');
    }
}
