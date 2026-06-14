<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\IntegrationSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(private readonly IntegrationSettingsService $settings) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('admin.integrations.view'), 403);

        $org = $request->user()->organization;

        return Inertia::render('Admin/Integrations/Index', [
            'schema'           => $this->settings->schema(),
            'values'           => $this->settings->all($org),
            'organizationName' => $org !== null ? $org->name : '-',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('admin.integrations.manage'), 403);

        $schema = $this->settings->schema();
        $group  = (string) $request->input('group');

        if (! isset($schema[$group])) {
            return back()->withErrors(['group' => 'Grup integrasi tidak dikenal.']);
        }

        // Build per-field validation rules from the schema (types only).
        $rules = ['group' => ['required', 'string'], 'fields' => ['array']];
        foreach ($schema[$group]['fields'] as $field) {
            $key   = $field['key'];
            $rule  = ['nullable'];
            switch ($field['type']) {
                case 'number':
                    $rule[] = 'numeric';
                    break;
                case 'bool':
                    $rule[] = 'boolean';
                    break;
                case 'select':
                    if (! empty($field['options'])) {
                        $rule[] = 'in:' . implode(',', $field['options']);
                    } else {
                        $rule[] = 'string';
                    }
                    break;
                default:
                    $rule[] = 'string';
                    $rule[] = 'max:2000';
            }
            $rules["fields.{$key}"] = $rule;
        }

        $request->validate($rules);

        $org = $request->user()->organization;
        abort_unless($org !== null, 403);

        $this->settings->save($org, [
            'group'  => $group,
            'fields' => (array) $request->input('fields', []),
        ], $request->user());

        return back()->with('success', 'Pengaturan integrasi disimpan.');
    }
}
