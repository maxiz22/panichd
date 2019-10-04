<?php

namespace PanicHD\PanicHD\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use PanicHD\PanicHD\Models\Configuration;
use PanicHD\PanicHD\Models\Setting;

class ConfigurationsController extends Controller
{
	/**
	* Display a listing of the Setting.
	*
	* @return Response
	*/
	public function index()
	{
		$configurations = Configuration::all();
		$configurations_by_sections = ['init' => [], 'table' => [], 'features' => [], 'email' => [], 'tickets' => [], 'perms' => [], 'editor' => [], 'other' => []];
		$init_section = ['main_route', 'main_route_path', 'admin_route', 'admin_route_path', 'master_template', 'member_model_class', 'routes', 'include_font_awesome', 'user_route'];
        $table_section = ['subject_content_column', 'list_text_max_length', 'check_last_update_seconds', 'length_menu', 'max_agent_buttons', 'calendar_month_filter', 'paginate_items'];
        $features_section = ['departments_feature', 'departments_notices_feature', 'ticket_attachments_feature'];
        $email_section = ['status_notification', 'comment_notification', 'queue_emails', 'assigned_notification',
		'list_owner_notification', 'status_owner_notification',
		'email.template', 'email.owner.newticket.template', 'email.header', 'email.signoff', 'email.signature', 'email.dashboard',
		'email.google_plus_link', 'email.facebook_link', 'email.twitter_link', 'email.footer', 'email.footer_link',
		'email.color_body_bg', 'email.color_header_bg', 'email.color_content_bg', 'email.color_footer_bg',
		'email.color_button_bg', 'email.account.name', 'email.account.mailbox', 'custom_recipients' ];
		$tickets_section = ['default_priority_id', 'default_status_id', 'default_close_status_id', 'default_reopen_status_id',
            'attachments_ticket_max_size', 'attachments_ticket_max_files_num', 'attachments_mimes',
            'attachments_path', 'thumbnails_path', 'oldest_year', 'html_replacements', 'use_default_status_id', 'delete_modal_type', 'new_ticket_autoassign'];
		$perms_section = ['agent_restrict', 'close_ticket_perm', 'reopen_ticket_perm'];
		$editor_section = ['editor_enabled', 'editor_html_highlighter', 'codemirror_theme',
			'summernote_locale', 'summernote_options_json_file', 'summernote_options_user', 'purifier_config', ];

        // Split them into configurations sections for tabs
        foreach ($configurations as $config_item) {
            //trim long values (ex serialised arrays)
            $config_item->value = $config_item->getShortContent(25, 'value');
            $config_item->default = $config_item->getShortContent(25, 'default');

            if (in_array($config_item->slug, $init_section)) {
                $configurations_by_sections['init'][] = $config_item;
            
            } elseif (in_array($config_item->slug, $table_section)) {
                $configurations_by_sections['table'][] = $config_item;
            
            } elseif (in_array($config_item->slug, $features_section)) {
                $configurations_by_sections['features'][] = $config_item;
            
            } elseif (in_array($config_item->slug, $email_section)) {
                $configurations_by_sections['email'][] = $config_item;
            
            } elseif (in_array($config_item->slug, $tickets_section)) {
                $configurations_by_sections['tickets'][] = $config_item;
            
            } elseif (in_array($config_item->slug, $perms_section)) {
                $configurations_by_sections['perms'][] = $config_item;
            
            } elseif (in_array($config_item->slug, $editor_section)) {
                $configurations_by_sections['editor'][] = $config_item;
            
            } else {
                $configurations_by_sections['other'][] = $config_item;
            }
        }

        return view('panichd::admin.configuration.index', compact('configurations', 'configurations_by_sections'));
    }

    /**
     * Show the form for creating a new Setting.
     *
     * @return Response
     */
    public function create()
    {
        return view('panichd::admin.configuration.create');
    }

    /**
     * Store a newly created Configuration in storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $input = $request->all();

        $configuration = new Configuration();
        $configuration->create($input);

        Session::flash('configuration', 'Setting saved successfully.');
        \Cache::forget('panichd::settings'); // refresh cached settings
        return redirect()->action('\PanicHD\PanicHD\Controllers\ConfigurationsController@index');
    }

    /**
     * Show the form for editing the specified Configuration.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $configuration = Configuration::findOrFail($id);
        $should_serialize = Setting::is_serialized($configuration->value);
        $default_serialized = Setting::is_serialized($configuration->default);

        return view('panichd::admin.configuration.edit', compact('configuration', 'should_serialize', 'default_serialized'));
    }

    /**
     * Update the specified Configuration in storage.
     *
     * @param int     $id
     * @param Request $request
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $configuration = Configuration::findOrFail($id);

        $value = $request->value;

        if ($request->serialize) {
            //if(!Hash::check($request->password, auth()->user()->password)){
            if (!Auth::attempt($request->only('password'), false, false)) {
                return back()->withErrors([trans('panichd::admin.config-edit-auth-failed')]);
            }
            if (false === eval('$value = serialize('.$value.');')) {
                return back()->withErrors([trans('panichd::admin.config-edit-eval-error')]);
            }
        }

        $configuration->update(['value' => $value, 'lang' => $request->lang]);

        Session::flash('configuration', trans('panichd::admin.config-update-confirm', ['name' => $request->name]));
        // refresh cached settings
        \Cache::forget('panichd::settings');
        \Cache::forget('panichd::settings.'.$configuration->slug);

        return redirect()->action('\PanicHD\PanicHD\Controllers\ConfigurationsController@index');
    }

    /**
     * Update the specified Configuration in storage.
     *
     * @param int     $id
     * @param Request $request
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, $id)
    {
        $configuration = Configuration::findOrFail($id);
        $clone = clone $configuration;

        $value = $request->value;

        $configuration->delete();

        Session::flash('status', trans('panichd::admin.config-delete-confirm', ['name' => $clone->slug]));
        // refresh cached settings
        \Cache::forget('panichd::settings');
        \Cache::forget('panichd::settings.'.$clone->slug);

        return redirect()->action('\PanicHD\PanicHD\Controllers\ConfigurationsController@index');
    }
}
