<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\DriveFile;
use App\Models\DriveFolder;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds a believable network: a handful of colleagues, a populated
     * newsfeed, drives with public and private files, and live chats.
     *
     * Every demo account uses the password "password".
     */
    public function run(): void
    {
        $people = [
            [
                'name' => 'Nimal Perera', 'username' => 'nimal',
                'designation' => 'Medical Officer, Grade I',
                'qualifications' => 'MBBS (Colombo), MSc Health Informatics',
                'skills' => 'Health informatics, Data analysis, Clinical audit, Training',
                'bio' => "I look after the clinic information systems and help teams make sense of their data.\nAlways happy to sit down with anyone who wants a dashboard built.",
                'minutes' => 0,
            ],
            [
                'name' => 'Sanduni Fernando', 'username' => 'sanduni',
                'designation' => 'Chief Pharmacist',
                'qualifications' => 'BPharm (Peradeniya), MSc Clinical Pharmacy',
                'skills' => 'Inventory management, Clinical pharmacy, Procurement',
                'bio' => 'Running the dispensary and the stock system. Ask me before you order anything.',
                'minutes' => 0,
            ],
            [
                'name' => 'Ruwan Jayasuriya', 'username' => 'ruwan',
                'designation' => 'Systems Administrator',
                'qualifications' => 'BSc Computer Science, RHCE',
                'skills' => 'Linux, Networking, MySQL, Backups, Laravel',
                'bio' => 'If the network is slow, I probably already know. Server room, second floor.',
                'minutes' => 1,
            ],
            [
                'name' => 'Dilani Wickramasinghe', 'username' => 'dilani',
                'designation' => 'Nursing Officer In-Charge',
                'qualifications' => 'Diploma in Nursing, BSc Nursing (OUSL)',
                'skills' => 'Ward management, Infection control, Staff rostering',
                'bio' => 'Ward 4. Rosters go up every Thursday.',
                'minutes' => 0,
            ],
            [
                'name' => 'Kasun Bandara', 'username' => 'kasun',
                'designation' => 'Medical Laboratory Technologist',
                'qualifications' => 'BSc MLS (Jayewardenepura)',
                'skills' => 'Haematology, Quality control, LIS',
                'bio' => 'Lab turnaround times are on the network drive, updated weekly.',
                'minutes' => 240,
            ],
            [
                'name' => 'Ayesha Rahman', 'username' => 'ayesha',
                'designation' => 'Health Education Officer',
                'qualifications' => 'BA Communication, Dip. Public Health',
                'skills' => 'Health promotion, Graphic design, Community outreach, Training',
                'bio' => 'I make the posters and run the awareness programmes. Send me your ideas.',
                'minutes' => 3,
            ],
            [
                'name' => 'Tharindu Silva', 'username' => 'tharindu',
                'designation' => 'Accounts Officer',
                'qualifications' => 'AAT, Reading CA Sri Lanka',
                'skills' => 'Budgeting, Procurement, Excel, Reporting',
                'bio' => 'Claims by the 25th, please. Forms are in the public drive.',
                'minutes' => 1440,
            ],
            [
                'name' => 'Menaka Gunawardena', 'username' => 'menaka',
                'designation' => 'Public Health Inspector',
                'qualifications' => 'Dip. Public Health Inspection',
                'skills' => 'Field surveillance, Food safety, Dengue control, Reporting',
                'bio' => 'Out in the field most mornings. Reachable here in the afternoons.',
                'minutes' => 55,
            ],
        ];

        $users = [];

        foreach ($people as $person) {
            $minutes = $person['minutes'];
            unset($person['minutes']);

            $users[$person['username']] = User::create($person + [
                'password' => 'password',
                'last_seen_at' => now()->subMinutes($minutes),
                'created_at' => now()->subDays(count($users) * 4 + 6),
            ]);
        }

        $this->seedFeed($users);
        $this->seedDrives($users);
        $this->seedChats($users);

        $this->command->newLine();
        $this->command->info('Seeded '.count($users).' colleagues. Sign in with username "nimal" and password "password".');
    }

    /* ------------------------------------------------------------------ */

    protected function seedFeed(array $users): void
    {
        $posts = [
            [
                'by' => 'nimal', 'type' => 'standard', 'hours' => 2,
                'body' => "The new clinic dashboard is live on the intranet. Attendance, waiting times and referrals all update every fifteen minutes.\n\nIf a figure looks wrong, tell me rather than working around it - that is usually a mapping problem I can fix in a few minutes.",
            ],
            [
                'by' => 'ayesha', 'type' => 'colored', 'theme' => 'citrus', 'hours' => 5,
                'body' => 'World Health Day programme this Friday, 9 a.m. at the main hall. Everyone welcome.',
            ],
            [
                'by' => 'sanduni', 'type' => 'standard', 'hours' => 9,
                'body' => "Stock check finished for the quarter. Two things worth knowing:\n\n1. Paracetamol syrup is down to six weeks of cover, order is placed.\n2. Please stop keeping ward stock in the old cupboards - we cannot count what we cannot see.\n\nThe full reconciliation sheet is in my drive, published to the network.",
            ],
            [
                'by' => 'ruwan', 'type' => 'standard', 'hours' => 26,
                'body' => "Scheduled maintenance on the file server this Saturday from 7 p.m. Expect about an hour of downtime.\n\nSaverMate, the HMIS and the lab system will all be unavailable. Nothing is lost - the queue picks up once it is back.",
            ],
            [
                'by' => 'dilani', 'type' => 'colored', 'theme' => 'mint', 'hours' => 30,
                'body' => 'Ward 4 hit 100 days without a single pressure ulcer. Proud of this team.',
            ],
            [
                'by' => 'menaka', 'type' => 'standard', 'hours' => 52,
                'body' => "Dengue larval survey for the quarter is done. Three premises in the Kadawatha division need follow-up visits.\n\nI have put the field maps and the inspection photos on the network drive so the whole team can see the hotspots.",
            ],
            [
                'by' => 'kasun', 'type' => 'standard', 'hours' => 74,
                'body' => "Reminder from the lab: please write the collection time on every sample, not just the date. We are rejecting about a dozen samples a week purely for this.",
            ],
            [
                'by' => 'tharindu', 'type' => 'colored', 'theme' => 'ocean', 'hours' => 96,
                'body' => 'Travel claims for this month close on the 25th. Forms are on the network drive.',
            ],
            [
                'by' => 'nimal', 'type' => 'colored', 'theme' => 'violet', 'hours' => 120,
                'body' => 'Welcome to SaverMate. Say hello, share what you are working on, and keep your files where colleagues can find them.',
            ],
        ];

        $comments = [
            0 => [
                ['by' => 'dilani', 'body' => 'This is excellent. Waiting times for Ward 4 finally match what we see on the floor.'],
                ['by' => 'ruwan', 'body' => 'Added it to the browser homepage on all the front desk machines.'],
            ],
            2 => [
                ['by' => 'dilani', 'body' => 'Noted on the cupboards. I will have the ward stock moved to the new shelves by Friday.'],
            ],
            3 => [
                ['by' => 'sanduni', 'body' => 'Can we push it to 8 p.m.? The evening dispensing run finishes around half past seven.'],
            ],
            5 => [
                ['by' => 'ayesha', 'body' => 'If you send me the maps I can turn them into a poster for the community centre.'],
            ],
        ];

        $replies = [
            3 => ['by' => 'ruwan', 'body' => 'Fine by me, 8 p.m. it is. I will put a notice on the boards tomorrow.'],
            5 => ['by' => 'menaka', 'body' => 'Will do, thank you. Sending the shapefiles across this evening.'],
        ];

        $reactionTypes = array_keys(Reaction::TYPES);
        $usernames = array_keys($users);
        $created = [];

        foreach ($posts as $index => $spec) {
            $at = now()->subHours($spec['hours']);

            $post = Post::create([
                'user_id' => $users[$spec['by']]->id,
                'type' => $spec['type'],
                'body' => $spec['body'],
                'theme' => $spec['theme'] ?? null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            $created[$index] = $post;

            // Spread a varied but deterministic set of reactions over each post.
            $reactorCount = max(2, 7 - $index);

            foreach (array_slice($usernames, 0, $reactorCount) as $slot => $username) {
                if ($users[$username]->id === $post->user_id) {
                    continue;
                }

                Reaction::create([
                    'post_id' => $post->id,
                    'user_id' => $users[$username]->id,
                    'type' => $reactionTypes[($index + $slot) % count($reactionTypes)],
                    'created_at' => $at->copy()->addMinutes(10 + $slot * 7),
                    'updated_at' => $at->copy()->addMinutes(10 + $slot * 7),
                ]);

                Alert::record([
                    'user_id' => $post->user_id,
                    'actor_id' => $users[$username]->id,
                    'type' => 'reaction',
                    'post_id' => $post->id,
                    'preview' => Reaction::label($reactionTypes[($index + $slot) % count($reactionTypes)]).' on your post',
                    'read_at' => $index > 1 ? $at->copy()->addHour() : null,
                    'created_at' => $at->copy()->addMinutes(10 + $slot * 7),
                    'updated_at' => $at->copy()->addMinutes(10 + $slot * 7),
                ]);
            }
        }

        foreach ($comments as $index => $thread) {
            foreach ($thread as $slot => $spec) {
                $at = $created[$index]->created_at->copy()->addMinutes(35 + $slot * 25);

                $comment = Comment::create([
                    'post_id' => $created[$index]->id,
                    'user_id' => $users[$spec['by']]->id,
                    'body' => $spec['body'],
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                Alert::record([
                    'user_id' => $created[$index]->user_id,
                    'actor_id' => $users[$spec['by']]->id,
                    'type' => 'comment',
                    'post_id' => $created[$index]->id,
                    'comment_id' => $comment->id,
                    'preview' => \Illuminate\Support\Str::limit($spec['body'], 70),
                    'read_at' => $index > 1 ? $at->copy()->addHour() : null,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                if ($slot === 0 && isset($replies[$index])) {
                    $replyAt = $at->copy()->addMinutes(18);

                    $reply = Comment::create([
                        'post_id' => $created[$index]->id,
                        'user_id' => $users[$replies[$index]['by']]->id,
                        'parent_id' => $comment->id,
                        'body' => $replies[$index]['body'],
                        'created_at' => $replyAt,
                        'updated_at' => $replyAt,
                    ]);

                    Alert::record([
                        'user_id' => $comment->user_id,
                        'actor_id' => $reply->user_id,
                        'type' => 'reply',
                        'post_id' => $created[$index]->id,
                        'comment_id' => $reply->id,
                        'preview' => \Illuminate\Support\Str::limit($replies[$index]['body'], 70),
                        'created_at' => $replyAt,
                        'updated_at' => $replyAt,
                    ]);
                }
            }
        }
    }

    /* ------------------------------------------------------------------ */

    protected function seedDrives(array $users): void
    {
        $tree = [
            'nimal' => [
                ['folder' => 'Clinic Reports', 'public' => true, 'files' => [
                    ['Clinic attendance - Q3.csv', 'text/csv', $this->csv([
                        ['Month', 'Attendance', 'New patients', 'Referrals'],
                        ['July', '4120', '318', '96'],
                        ['August', '4406', '341', '104'],
                        ['September', '4287', '329', '88'],
                    ])],
                    ['Dashboard user guide.txt', 'text/plain', "CLINIC DASHBOARD - QUICK GUIDE\n\n1. Open http://intranet/dashboard from any ward machine.\n2. Pick your clinic from the dropdown at the top left.\n3. Figures refresh every 15 minutes; the timestamp is in the footer.\n4. Anything that looks wrong is usually a mapping issue - send me the\n   clinic code and the date range and I will check it.\n"],
                ]],
                ['folder' => 'Drafts', 'public' => false, 'files' => [
                    ['Informatics plan 2026 (draft).txt', 'text/plain', "DRAFT - not for circulation yet.\n\nPriorities for next year:\n- Single sign-on across HMIS, lab and pharmacy\n- Offline-capable ward tablets\n- Replace the nightly CSV exports with a proper API\n"],
                ]],
            ],
            'sanduni' => [
                ['folder' => 'Pharmacy', 'public' => true, 'files' => [
                    ['Stock reconciliation - Q3.csv', 'text/csv', $this->csv([
                        ['Item', 'Opening', 'Received', 'Issued', 'Closing', 'Weeks cover'],
                        ['Paracetamol syrup 125mg/5ml', '860', '400', '1010', '250', '6'],
                        ['Amoxicillin caps 250mg', '5400', '2000', '4850', '2550', '14'],
                        ['Metformin tabs 500mg', '12800', '6000', '11200', '7600', '19'],
                        ['Salbutamol inhaler', '310', '150', '295', '165', '11'],
                    ])],
                    ['Ordering cut-off dates.txt', 'text/plain', "ORDERING CUT-OFF DATES\n\nRoutine orders: the 10th and the 24th of each month.\nUrgent requests: any time, but call the dispensary first.\n\nNothing is ordered without a signed requisition.\n"],
                ]],
            ],
            'tharindu' => [
                ['folder' => 'Forms', 'public' => true, 'files' => [
                    ['Travel claim form.txt', 'text/plain', "TRAVEL CLAIM\n\nName: ______________________  Designation: ______________________\n\nDate | From | To | Purpose | Mode | Amount\n-----+------+----+---------+------+-------\n     |      |    |         |      |\n     |      |    |         |      |\n\nTotal claimed: ____________\n\nClaimant signature: ____________   Date: __________\nApproved by: ____________________   Date: __________\n\nSubmit to Accounts by the 25th of the month.\n"],
                    ['Petty cash reimbursement.txt', 'text/plain', "PETTY CASH REIMBURSEMENT\n\nAttach original receipts. Claims over Rs. 5,000 need prior approval.\n\nItem | Receipt no. | Amount\n-----+-------------+-------\n     |             |\n"],
                ]],
            ],
            'kasun' => [
                ['folder' => 'Laboratory', 'public' => true, 'files' => [
                    ['Turnaround times - weekly.csv', 'text/csv', $this->csv([
                        ['Test', 'Target (hrs)', 'Median (hrs)', 'Within target'],
                        ['Full blood count', '4', '2.6', '94%'],
                        ['Blood culture', '48', '44.0', '88%'],
                        ['Liver profile', '8', '6.4', '91%'],
                        ['Dengue NS1', '6', '3.9', '97%'],
                    ])],
                    ['Sample rejection criteria.txt', 'text/plain', "SAMPLE REJECTION CRITERIA\n\nA sample is rejected when:\n- The collection TIME is missing (date alone is not enough)\n- The label does not match the request form\n- The tube is the wrong type for the test\n- The sample is haemolysed or clotted\n- The volume is below the minimum for the assay\n\nRejections are logged and reported back to the requesting ward daily.\n"],
                ]],
            ],
            'menaka' => [
                ['folder' => 'Field Surveillance', 'public' => true, 'files' => [
                    ['Larval survey - Kadawatha.csv', 'text/csv', $this->csv([
                        ['Division', 'Premises visited', 'Positive', 'House index', 'Action'],
                        ['Kadawatha North', '142', '9', '6.3%', 'Follow-up'],
                        ['Kadawatha South', '128', '4', '3.1%', 'Monitor'],
                        ['Mahara', '96', '2', '2.1%', 'Clear'],
                    ])],
                ]],
            ],
            'ayesha' => [
                ['folder' => 'Health Promotion', 'public' => true, 'files' => [
                    ['World Health Day programme.txt', 'text/plain', "WORLD HEALTH DAY - PROGRAMME\n\n09:00  Welcome - Head of Institution\n09:20  Keynote: non-communicable disease screening\n10:00  Tea\n10:20  Ward exhibitions open (main hall)\n11:30  Free BP and blood sugar screening\n13:00  Lunch and close\n\nEveryone is welcome. No registration needed.\n"],
                ]],
                ['folder' => 'Poster artwork', 'public' => false, 'files' => [
                    ['Dengue poster - working copy.txt', 'text/plain', "Working notes for the dengue poster.\n\nHeadline options:\n- 'Ten minutes a week keeps dengue out'\n- 'Check. Empty. Cover.'\n\nStill waiting on the final figures from Menaka.\n"],
                ]],
            ],
            'ruwan' => [
                ['folder' => 'IT Notices', 'public' => true, 'files' => [
                    ['Server maintenance window.txt', 'text/plain', "SCHEDULED MAINTENANCE\n\nWhen:  Saturday, 20:00 - 21:00\nWhat:  File server firmware and OS patches\n\nUnavailable during the window:\n- SaverMate\n- HMIS\n- Laboratory information system\n- Shared network folders\n\nNothing queued is lost. Anything you submit during the window is\nprocessed once the server is back.\n"],
                ]],
                ['folder' => 'Server configs', 'public' => false, 'files' => [
                    ['backup-schedule.txt', 'text/plain', "BACKUP SCHEDULE (internal)\n\nNightly  01:00  Incremental, on-site NAS\nWeekly   Sun 02:00  Full, on-site NAS\nMonthly  1st 03:00  Full, off-site copy\n\nRestores are tested on the first Monday of each month.\n"],
                ]],
            ],
            'dilani' => [
                ['folder' => 'Ward 4', 'public' => true, 'files' => [
                    ['Nursing roster - this month.csv', 'text/csv', $this->csv([
                        ['Week', 'Morning', 'Evening', 'Night'],
                        ['Week 1', 'Dilani, Kumari', 'Shanika', 'Pradeep'],
                        ['Week 2', 'Shanika, Nadee', 'Kumari', 'Dilani'],
                        ['Week 3', 'Pradeep, Dilani', 'Nadee', 'Kumari'],
                        ['Week 4', 'Kumari, Shanika', 'Dilani', 'Nadee'],
                    ])],
                ]],
            ],
        ];

        foreach ($tree as $username => $folders) {
            $user = $users[$username];

            foreach ($folders as $spec) {
                $folder = DriveFolder::create([
                    'user_id' => $user->id,
                    'parent_id' => null,
                    'name' => $spec['folder'],
                    'is_public' => $spec['public'],
                    'created_at' => now()->subDays(9),
                    'updated_at' => now()->subDays(9),
                ]);

                foreach ($spec['files'] as $slot => [$name, $mime, $contents]) {
                    $path = 'drive/'.$user->id.'/'.\Illuminate\Support\Str::uuid().'.'.
                        (pathinfo($name, PATHINFO_EXTENSION) ?: 'txt');

                    Storage::disk(DriveFile::DISK)->put($path, $contents);

                    DriveFile::create([
                        'user_id' => $user->id,
                        'folder_id' => $folder->id,
                        'name' => $name,
                        'path' => $path,
                        'mime' => $mime,
                        'size' => strlen($contents),
                        'is_public' => $spec['public'],
                        'created_at' => now()->subDays(8)->addHours($slot),
                        'updated_at' => now()->subDays(8)->addHours($slot),
                    ]);
                }
            }
        }
    }

    protected function csv(array $rows): string
    {
        return implode("\n", array_map(fn ($row) => implode(',', $row), $rows))."\n";
    }

    /* ------------------------------------------------------------------ */

    protected function seedChats(array $users): void
    {
        $threads = [
            [
                'between' => ['nimal', 'ruwan'],
                'messages' => [
                    ['ruwan', 'Did you see the maintenance notice? Saturday 8 p.m. now, Sanduni asked me to move it.', 180, true],
                    ['nimal', 'Saw it. Fine by me - nothing of mine runs on a Saturday evening.', 176, true],
                    ['nimal', 'One thing: can you take a database snapshot before the patch? I would rather have a clean restore point.', 175, true],
                    ['ruwan', 'Already scripted. Snapshot at 19:45, kept for a fortnight.', 171, true],
                    ['ruwan', 'I put the full notice on the drive if you want to forward it to the clinics.', 170, true],
                    ['nimal', 'Perfect, thanks. I will send it round in the morning.', 168, true],
                ],
            ],
            [
                'between' => ['nimal', 'sanduni'],
                'messages' => [
                    ['sanduni', 'The reconciliation sheet is published now. Can you pull the issue figures into the dashboard?', 48, true],
                    ['nimal', 'Yes. Is the item coding the same as last quarter?', 46, true],
                    ['sanduni', 'Same codes, but I added a weeks-of-cover column at the end.', 44, true],
                    ['nimal', 'Good, that is the useful one. I will add a low-cover alert under six weeks.', 42, true],
                    ['sanduni', 'That would save me a lot of chasing. Thank you.', 40, false],
                ],
            ],
            [
                'between' => ['nimal', 'dilani'],
                'messages' => [
                    ['dilani', 'Ward 4 waiting times look right on the new dashboard. Finally.', 26, true],
                    ['nimal', 'Glad to hear it. The old version was averaging across the whole clinic, which flattered everybody.', 24, true],
                    ['dilani', 'Could you add a night-shift breakdown? That is where our delays actually sit.', 22, false],
                    ['dilani', 'No rush - whenever you have a moment.', 21, false],
                ],
            ],
            [
                'between' => ['nimal', 'ayesha'],
                'messages' => [
                    ['ayesha', 'Programme for Friday is on the drive. Could you put the attendance numbers on a slide for the keynote?', 7, true],
                    ['nimal', 'Sure. Quarterly figures or the twelve month trend?', 6, true],
                    ['ayesha', 'Twelve months, it tells a better story.', 5, false],
                ],
            ],
            [
                'between' => ['sanduni', 'dilani'],
                'messages' => [
                    ['sanduni', 'About the ward cupboards - can I send someone up on Friday morning to count what is there?', 30, true],
                    ['dilani', 'Yes, after the ward round please. Say half past ten.', 28, true],
                ],
            ],
        ];

        foreach ($threads as $thread) {
            [$a, $b] = $thread['between'];
            $conversation = Conversation::between($users[$a], $users[$b]);
            $lastAt = null;

            foreach ($thread['messages'] as [$sender, $body, $hoursAgo, $read]) {
                $at = now()->subHours($hoursAgo);
                $lastAt = $at;

                $message = $conversation->messages()->create([
                    'sender_id' => $users[$sender]->id,
                    'body' => $body,
                    'read_at' => $read ? $at->copy()->addMinutes(4) : null,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                if (! $read) {
                    Alert::record([
                        'user_id' => $conversation->partnerFor($users[$sender])->id,
                        'actor_id' => $users[$sender]->id,
                        'type' => 'message',
                        'conversation_id' => $conversation->id,
                        'preview' => \Illuminate\Support\Str::limit($body, 70),
                        'created_at' => $at,
                        'updated_at' => $at,
                    ]);
                }
            }

            // Sanduni shares the reconciliation sheet straight from her drive.
            if ($a === 'nimal' && $b === 'sanduni') {
                $file = DriveFile::where('user_id', $users['sanduni']->id)
                    ->where('name', 'like', 'Stock reconciliation%')
                    ->first();

                if ($file) {
                    $at = now()->subHours(39);

                    $message = $conversation->messages()->create([
                        'sender_id' => $users['sanduni']->id,
                        'body' => 'Here is the sheet, straight from my drive.',
                        'read_at' => null,
                        'created_at' => $at,
                        'updated_at' => $at,
                    ]);

                    $message->attachments()->create([
                        'source' => 'drive',
                        'drive_file_id' => $file->id,
                        'name' => $file->name,
                        'path' => $file->path,
                        'mime' => $file->mime,
                        'size' => $file->size,
                    ]);

                    $lastAt = $at;

                    Alert::record([
                        'user_id' => $users['nimal']->id,
                        'actor_id' => $users['sanduni']->id,
                        'type' => 'message',
                        'conversation_id' => $conversation->id,
                        'preview' => 'Here is the sheet, straight from my drive.',
                        'created_at' => $at,
                        'updated_at' => $at,
                    ]);
                }
            }

            $conversation->update(['last_message_at' => $lastAt ?? now()]);
        }
    }
}
