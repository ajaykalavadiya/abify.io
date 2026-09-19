<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveSiteController extends Controller
{
    /**
     * Switch the site the user is working in. Stored on the user rather than
     * carried in the URL, so every site-scoped page follows the selection.
     */
    public function update(Request $request, Company $account): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer'],
        ]);

        // Scoped to the account being viewed, so a site id from another
        // tenant can't be selected.
        $site = $account->sites()->whereKey($validated['site_id'])->first();

        abort_if($site === null, 404);

        $request->user()->forceFill(['current_site_id' => $site->id])->save();

        return to_route('site.overview', $account->uid);
    }
}
