<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Alert;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'settings' => Setting::cached(),
            'recipients' => User::whereNull('suspended_at')->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'announcement' => ['nullable', 'string', 'max:300'],
            'announcement_tone' => ['required', Rule::in(['info', 'warning', 'danger'])],
        ]);

        $values = [
            'registration_open' => $request->boolean('registration_open') ? '1' : '0',
            'announcement' => trim((string) ($data['announcement'] ?? '')),
            'announcement_tone' => $data['announcement_tone'],
        ];

        $before = Setting::cached();
        Setting::put($values);

        $changed = array_keys(array_diff_assoc($values, array_intersect_key($before, $values)));
        if ($changed) {
            AdminLog::record('settings.update', 'Changed site settings: '.implode(', ', $changed));
        }

        return back()->with('status', 'Site settings saved.');
    }

    /** Drop a notification into every active member's bell. */
    public function broadcast(Request $request): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:250']]);

        $sender = $request->user()->id;
        $now = now();
        $sent = 0;

        User::whereNull('suspended_at')->whereKeyNot($sender)->select('id')
            ->chunkById(500, function ($users) use ($sender, $data, $now, &$sent) {
                Alert::insert($users->map(fn ($u) => [
                    'user_id' => $u->id,
                    'actor_id' => $sender,
                    'type' => 'announcement',
                    'preview' => $data['message'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());

                $sent += $users->count();
            });

        AdminLog::record('broadcast', 'Sent a notification to '.$sent.' members: "'.Str::limit($data['message'], 80).'"');

        return back()->with('status', 'Notification sent to '.$sent.' '.Str::plural('member', $sent).'.');
    }

    public function activity(Request $request): View
    {
        $query = AdminLog::with('user')->latest();

        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('description', 'like', "%{$term}%")->orWhere('action', 'like', "%{$term}%"));
        }

        return view('admin.activity', ['logs' => $query->paginate(40)->withQueryString()]);
    }
}
