<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\TradeHubContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContentController extends Controller
{
    private const SETTINGS = [
        'site_name',
        'tagline',
        'hero_title',
        'hero_text',
        'about_text',
        'contact_email',
        'contact_phone',
        'commission_rate',
    ];

    public function index(Request $request, TradeHubContent $content): View
    {
        if ($content->wordpressConfigured()) {
            return view('admin.wordpress-content', [
                'wordpressAdminUrl' => $content->wordpressAdminUrl(),
            ]);
        }

        return view('admin.content', [
            'settings' => $content->settings(),
            'editingAnnouncement' => $request->filled('announcement')
                ? Announcement::query()->findOrFail($request->integer('announcement'))
                : null,
            'announcements' => Announcement::query()->orderByDesc('created_at')->get(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'min:2', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:140'],
            'hero_title' => ['nullable', 'string', 'max:140'],
            'hero_text' => ['nullable', 'string', 'max:500'],
            'about_text' => ['nullable', 'string', 'max:800'],
            'contact_email' => ['required', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'commission_rate' => ['required', 'numeric', 'between:0,100'],
        ]);

        foreach (self::SETTINGS as $key) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) ($data[$key] ?? '')]
            );
        }

        return back()->with('success', 'Website content updated.');
    }

    public function saveAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'min:0'],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'min:5', 'max:1000'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $announcement = ! empty($data['id'])
            ? Announcement::query()->findOrFail($data['id'])
            : new Announcement;
        $announcement->fill([
            'title' => $data['title'],
            'body' => $data['body'],
            'is_published' => $request->boolean('is_published'),
        ]);
        if ($announcement->exists) {
            $announcement->save();
        } else {
            $announcement->created_at = now();
            $announcement->save();
        }

        return redirect()->route('admin.content')->with(
            'success',
            $announcement->wasRecentlyCreated ? 'Announcement published.' : 'Announcement updated.'
        );
    }

    public function announcementAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:announcements,id'],
            'action' => ['required', 'in:delete,toggle'],
        ]);
        $announcement = Announcement::query()->findOrFail($data['id']);

        if ($data['action'] === 'delete') {
            $announcement->delete();

            return back()->with('success', 'Announcement deleted.');
        }

        $announcement->is_published = ! $announcement->is_published;
        $announcement->save();

        return back()->with('success', 'Announcement visibility changed.');
    }
}
