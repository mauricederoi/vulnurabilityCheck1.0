<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe;
use App\Models\Business;
use App\Models\PlanOrder;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Utility;
use App\Models\LandingPageSection;
use Auth;

class HomeController extends Controller
{
    use \RachidLaasri\LaravelInstaller\Helpers\MigrationsHelper;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {

        if (\Auth::check()) {
            
                if (!file_exists(storage_path() . "/installed")) {
                    die;
                } else {
                    $uri = url()->full();
                    $segments = explode('/', str_replace('' . url('') . '', '', $uri));
                    $segments = $segments[1] ?? null;
                    if ($segments == null) {
                        $local = parse_url(config('app.url'))['host'];
                        // Get the request host
                        $remote = request()->getHost();
                        // Get the remote domain

                        // remove WWW
                        $remote = str_replace('www.', '', $remote);
                        $business = Business::where('domains', '=', $remote)->where('enable_domain', 'on')->first();
                        // If the domain exists
                        if ($business && $business->enable_domain == 'on') {
                            return app('App\Http\Controllers\BusinessController')->getcard($business->slug);
                        }
                        $sub_business = Business::where('subdomain', '=', $remote)->where('enable_subdomain', 'on')->first();
                        if ($sub_business && $sub_business->enable_subdomain == 'on') {
                            return app('App\Http\Controllers\BusinessController')->getcard($sub_business->slug);
                        }
                    }
                    if (\Auth::user()->type == 'super admin') {
                        $user = \Auth::user();
                        $user['total_user'] = $user->countCompany();
                        $user['total_paid_user'] = $user->countPaidCompany();
                        $user['total_orders'] = PlanOrder::total_orders();
                        $user['total_orders_price'] = PlanOrder::total_orders_price();
                        $user['total_plan'] = Plan::total_plan();
                        $user['most_purchese_plan'] = (!empty(Plan::most_purchese_plan()) ? Plan::most_purchese_plan()->total : 0);
                        $chartData = $this->getPlanOrderChart(['duration' => 'week']);


                        return view('dashboard.admin_dashboard', compact('user', 'chartData'));
                    } else {
                        $cards = \App\Models\Business::get();
                        $total_bussiness = \App\Models\Business::count();
                        $total_app = \App\Models\Appointment_deatail::count();
						$total_leads = \App\Models\LeadContact::where('user_id', \Auth::user()->id)->count();
						$total_contacts = \App\Models\Contacts::where('user_id', \Auth::user()->id)->count();
                        $total_staff = \App\Models\User::where('created_by', \Auth::user()->creatorId())->count();
                        $chartData = $this->getOrderChart(['duration' => 'week']);
                        $user = \Auth::user();
						
						$total_leads_count = \App\Models\LeadGeneration::where('user_id', \Auth::user()->id)->get();
						$mergedContent = [];

						foreach ($total_leads_count as $lead) {
							// Decode the JSON content
							$content = json_decode($lead->content, true);
							
							// Merge the decoded arrays
							if (is_array($content)) {
								$mergedContent = array_merge($mergedContent, $content);
							}
						}

						// Count the total number of items in the merged array
						$total_leads_campaign = count($mergedContent);

                        $visitor_url = \DB::table('visitor')->selectRaw("count('*') as total, url")->where('created_by', \Auth::user()->creatorId())->groupBy('url')->orderBy('total', 'DESC')->get();
                        $user_device = \DB::table('visitor')->selectRaw("count('*') as total, device")->where('created_by', \Auth::user()->creatorId())->groupBy('device')->orderBy('device', 'DESC')->get();
                        $user_browser = \DB::table('visitor')->selectRaw("count('*') as total, browser")->where('created_by', \Auth::user()->creatorId())->groupBy('browser')->orderBy('browser', 'DESC')->get();
                        $user_platform = \DB::table('visitor')->selectRaw("count('*') as total, platform")->where('created_by', \Auth::user()->creatorId())->groupBy('platform')->orderBy('platform', 'DESC')->get();
                        $devicearray = [];
                        $devicearray['label'] = [];
                        $devicearray['data'] = [];

                        foreach ($user_device as $name => $device) {
                            if (!empty($device->device)) {
                                $devicearray['label'][] = $device->device;
                            } else {
                                $devicearray['label'][] = 'Other';
                            }
                            $devicearray['data'][] = $device->total;
                        }

                        $browserarray = [];
                        $browserarray['label'] = [];
                        $browserarray['data'] = [];
						
						

                        foreach ($user_browser as $name => $browser) {
                            $browserarray['label'][] = $browser->browser;
                            $browserarray['data'][] = $browser->total;
                        }
                        $platformarray = [];
                        $platformarray['label'] = [];
                        $platformarray['data'] = [];

                        foreach ($user_platform as $name => $platform) {
                            $platformarray['label'][] = $platform->platform;
                            $platformarray['data'][] = $platform->total;
                        }
						
                        $users =\Auth::user();
                        if($users->current_business == 0)
                        {
                            $business = Business::where('created_by', \Auth::user()->creatorId())->first();
                            if($business)
                            {
                                $users->current_business=$business->id;
                                $users->save();
                            }
                            
                        }
                        $businessData = \App\Models\Business::where('id', $user->current_business)->where('created_by', \Auth::user()->creatorId())->first();
                        $qr_detail = '';
                        if (!empty($businessData)) {
                            $qr_detail = \App\Models\Businessqr::where('business_id', $businessData->id)->first();
                        }

                        return view('dashboard.dashboard', compact('total_bussiness', 'total_app','total_leads', 'total_leads_campaign','total_contacts', 'visitor_url', 'devicearray', 'browserarray', 'platformarray', 'chartData', 'cards', 'businessData', 'qr_detail', 'total_staff','user'));

                    }
                }
            
        } else {
            if (!file_exists(storage_path() . "/installed")) {
        
                //die;
            } else {
                if (Utility::getValByName('display_landing_page') == 'on') {
                    return view('landingpage::layouts.landingpage');
                } else {
                    return redirect('login');
                }
            }
        }
    }
    public function getOrderChart($arrParam)
    {
        $user = \Auth::user();

        $arrDuration = [];
        if ($arrParam['duration']) {
            if ($arrParam['duration'] == 'week') {
                $previous_month = strtotime("-1 week");
                for ($i = 0; $i < 7; $i++) {
                    $arrDuration[date('Y-m-d', $previous_month)] = date('d-M', $previous_month);
                    $previous_month = strtotime(date('Y-m-d', $previous_month) . " +1 day");
                }
            }
        }
        $arrTask = [];
        $arrTask['label'] = [];
        $arrTask['data'] = [];


        foreach ($arrDuration as $date => $label) {



            $data['visitor'] = \DB::table('visitor')->select(\DB::raw('count(*) as total'))->whereDate('created_at', '=', $date)->where('created_by', \Auth::user()->creatorId())->first();
            $uniq = \DB::table('visitor')->select('ip')->distinct()->whereDate('created_at', '=', $date)->where('created_by', \Auth::user()->creatorId())->get();

            $data['unique'] = $uniq->count();
            $arrTask['label'][] = $label;
            $arrTask['data'][] = $data['visitor']->total;
            $arrTask['unique_data'][] = $data['unique'];
        }

        $business = Business::where('created_by', \Auth::user()->creatorId())->get();
        $array_app = [];
        foreach ($business as $b) {
            $d['data'] = [];
            $d['name'] = $b->title;
            foreach ($arrDuration as $date => $label) {
                $d['data'][] = \DB::table('appointment_deatails')->where('business_id', $b->id)->where('created_by', \Auth::user()->creatorId())->whereDate('created_at', '=', $date)->count();
            }
            $array_app[] = $d;
        }
        $arrTask['data'] = $array_app;
        return $arrTask;
    }
    public function getPlanOrderChart($arrParam)
    {
        $arrDuration = [];
        if ($arrParam['duration']) {
            if ($arrParam['duration'] == 'week') {
                $previous_week = strtotime("-1 week +1 day");
                for ($i = 0; $i < 8; $i++) {
                    $arrDuration[date('Y-m-d', $previous_week)] = date('d-M', $previous_week);
                    $previous_week = strtotime(date('Y-m-d', $previous_week) . " +1 day");
                }
            }
        }

        $arrTask = [];
        $arrTask['label'] = [];
        $arrTask['data'] = [];
        foreach ($arrDuration as $date => $label) {

            $data = PlanOrder::select(\DB::raw('count(*) as total'))->whereDate('created_at', '=', $date)->first();
            $arrTask['label'][] = $label;
            $arrTask['data'][] = $data->total;
        }

        return $arrTask;
    }
    public function landingPage()
    {

        if (!file_exists(storage_path() . "/installed")) {
          
            die;
        } else {
            $local = parse_url(config('app.url'))['host'];

            // Get the request host
            $remote = request()->getHost();
            // Get the remote domain

            // remove WWW
            $remote = str_replace('www.', '', $remote);
            $business = Business::where('domains', '=', $remote)->where('enable_domain', 'on')->first();

            // If the domain exists
            if ($business && $business->enable_domain == 'on') {
                return app('App\Http\Controllers\BusinessController')->getcard($business->slug);
            }


            $sub_business = Business::where('subdomain', '=', $remote)->where('enable_subdomain', 'on')->first();

            if ($sub_business && $sub_business->enable_subdomain == 'on') {
                return app('App\Http\Controllers\BusinessController')->getcard($sub_business->slug);
            }
            if (\Auth::check()) {
                return $this->index();
            } else {

                if (Utility::getValByName('display_landing_page') != 'off') {
                    $get_section = LandingPageSection::orderBy('section_order', 'ASC')->get();
                    return view('layouts.landing', compact('get_section'));
                } else {
                    return redirect('login');
                }
            }
        }
    }

    // ChangeCurrentBusiness
    public function changeCurrantBusiness($business_id)
    {
        $user = Auth::user();
        $business = Business::find($business_id);

        if ($business_id != '0') {
            if ($business) {
                if ($business->status == "active") {
                    $user->current_business = $business_id;
                    $user->save();
                    return redirect()->back();
                } else {
                    return redirect()->back()->with('error', __('Business is locked'));
                }
            } else {
                return redirect()->back()->with('error', __('Business is not found'));
            }
        } else {
            $user->current_business = $business_id;
            $user->save();
            return redirect()->back();

        }


    }

}