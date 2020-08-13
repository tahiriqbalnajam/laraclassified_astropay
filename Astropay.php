<?php

namespace extras\plugins\astropay;

use App\Helpers\Number;
use App\Models\Permission;
use App\Models\Post;
use App\Models\PaymentMethod;
use App\Models\User;
use extras\plugins\astropay\app\Notifications\PaymentNotification;
use extras\plugins\astropay\app\Notifications\PaymentSent;
use Illuminate\Http\Request;
use App\Helpers\Payment;
use App\Models\Package;
use App\Models\Payment as PaymentModel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use extras\plugins\astropay\app\Notifications\AstroPayCard;

class Astropay extends Payment
{

	//Defined data
	private $base_url = "https://api.astropaycard.com/";
	private $sandbox_base_url = "https://sandbox-api.astropaycard.com/";
	
	private $validator_url = "verif/validator";
	private $transtatus_url = "verif/transtatus";
	/*     * *********** ************ ************* */
	//Credentials
	public $x_login;
	public $x_trans_key;
	//General settings
	private $x_version = "2.0"; //AstroPay API version (default "2.0")
	public $x_delim_char = "|"; //Field delimit character, the character that separates the fields (default "|")
	private $x_test_request; //Change to N for production
	private $x_duplicate_window = 120; //Time window of a transaction with the sames values is taken as duplicated (default 120)
	private $x_method = "CC";
	private $x_response_format = "json"; //Response format: "string", "json", "xml" (default: string) (recommended: json)
	/**
	 * Send Payment
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param \App\Models\Post $post
	 * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
	public static function sendPayment(Request $request, Post $post)
	{
		// Set URLs
		parent::$uri['previousUrl'] = str_replace(['#entryToken', '#entryId'], [$post->tmp_token, $post->id], parent::$uri['previousUrl']);
		parent::$uri['nextUrl'] = str_replace(['#entryToken', '#entryId', '#title'], [$post->tmp_token, $post->id, slugify($post->title)], parent::$uri['nextUrl']);
		parent::$uri['paymentCancelUrl'] = str_replace(['#entryToken', '#entryId'], [$post->tmp_token, $post->id], parent::$uri['paymentCancelUrl']);
		parent::$uri['paymentReturnUrl'] = str_replace(['#entryToken', '#entryId'], [$post->tmp_token, $post->id], parent::$uri['paymentReturnUrl']);
		
		// Get the Package
		$package = Package::find($request->input('package_id'));
		
		// Don't make a payment if 'price' = 0 or null
		if (empty($package) || $package->price <= 0) {
			return redirect(parent::$uri['previousUrl'] . '?error=package')->withInput();
		}
		
		// API Parameters
		$providerParams = [
			'cancelUrl'   => parent::$uri['paymentCancelUrl'],
			'returnUrl'   => parent::$uri['paymentReturnUrl'],
			'name'        => $package->name,
			'description' => $package->name,
			'amount'      => Number::toFloat($package->price),
			'currency'    => $package->currency_code,
		];
		
		// Local Parameters
		$localParams = [
			'payment_method_id' => $request->input('payment_method_id'),
			'post_id'           => $post->id,
			'package_id'        => $package->id,
		];
		$localParams = array_merge($localParams, $providerParams);

		try {
				
				$x_login = config('payment.astropay.x_login');
				$x_trans_key = config('payment.astropay.x_trans_key');
				$method = config('payment.paypal.mode');
				$x_version = "2.0";
				$x_duplicate_window = 120;
				$x_method = "CC";
				$x_response_format = "json";
				$x_delim_char = "|";

				if($method == 'sandbox'){
					$validator_url = "https://sandbox-api.astropaycard.com/verif/validator";
					$transtatus_url = "https://sandbox-api.astropaycard.com/verif/transtatus";
					$x_test_request = "N";
				} else{
					$validator_url = "https://api.astropaycard.com/verif/validator";
					$transtatus_url = "https://api.astropaycard.com/verif/transtatus";
					$x_test_request = "N";
				}
				//AstroPayCard class instance
				//Cardholder data
				$x_card_num = $request->input('card_number');
				$x_card_code = $request->input('cvv');
				$x_exp_date = $request->input('month').'/'.$request->input('year');

				//Transaction data
				$x_amount = Number::toFloat($package->price);
				$x_unique_id = '1234-85'.$post->id;
				$x_invoice_num = "pepito-".$post->id;

				$data['x_login'] = $x_login;
				$data['x_tran_key'] = $x_trans_key;
				$data['x_card_num'] = $x_card_num;
				$data['x_card_code'] = $x_card_code;
				$data['x_exp_date'] = $x_exp_date;
				$data['x_amount'] = $x_amount;
				$data['x_unique_id'] = $x_unique_id;
				$data['x_version'] = $x_version;
				$data['x_test_request'] = $x_test_request;
				$data['x_duplicate_window'] = $x_duplicate_window;
				$data['x_method'] = $x_method;
				$data['x_invoice_num'] = $x_invoice_num;
				$data['x_delim_char'] = $x_delim_char;
				$data['x_response_format'] = $x_response_format;
		
				$data['x_type'] = "AUTH_CAPTURE";
				$fields = '';
        $first = true;
        foreach ($data as $key => $value) {
            if (!$first) {
                $fields .= '&';
            }
            $fields .= "$key=$value";
            $first = false;
				}

				$ch = curl_init($validator_url);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

				$response = curl_exec($ch);
				curl_close($ch);
				
				//Use only in "string" format
				//$response = explode("|", $response);
				$response = json_decode($response);
				//Clasify the response data
				$response_code = $response->response_code;
				$response_subcode = $response->response_subcode;
				$response_reason_code = $response->response_reason_code;
				$response_reason_text = $response->response_reason_text;
				$response_transaction_id = $response->TransactionID;
				$response_amount = $response->x_amount;
				//die();
				//Evaluate if the transaction was succesfull or not
				if ($response_code == 1) {
						if ($x_amount == $response_amount) {
								$localParams['transaction_id'] = $response_transaction_id;
								return parent::paymentConfirmationActions($localParams, $post);
								//TODO!: Save $response_transaction_id and $response_authorization code for future use
						} else {
							return parent::paymentFailureActions($post, "Error: Invalid amount check.");
						}
				} else {
					return parent::paymentFailureActions($post, $response_reason_text);
					//If there are an error, it will be printed here.
					exit;
				}

		} catch (\Exception $e) {
			
			// Apply actions when API failed
			return parent::paymentApiErrorActions($post, $e);
			
		}
	}
	
	/**
	 * Save the payment and Send payment confirmation email
	 *
	 * @param Post $post
	 * @param $params
	 * @return PaymentModel|\Illuminate\Http\JsonResponse|null
	 */
	public static function register(Post $post, $params)
	{
		if (empty($post)) {
			return null;
		}
		
		// Update ad 'reviewed' & 'featured' fields
		$post->reviewed = ($post->reviewed == 1) ? 1 : 0;
		$post->featured = ($post->featured == 1) ? 1 : 0;
		$post->save();
		
		// Save the payment
		$paymentInfo = [
			'post_id'           => $post->id,
			'package_id'        => $params['package_id'],
			'payment_method_id' => $params['payment_method_id'],
			'transaction_id'    => (isset($params['transaction_id'])) ? $params['transaction_id'] : null,
			'active'            => 0,
		];
		$payment = new PaymentModel($paymentInfo);
		$payment->save();
		
		// SEND EMAILS
		
		// Get all admin users
		if (Permission::checkDefaultPermissions()) {
			$admins = User::permission(Permission::getStaffPermissions())->get();
		} else {
			$admins = User::where('is_admin', 1)->get();
		}
		
		// Send Payment Email Notifications
		if (config('settings.mail.payment_notification') == 1) {
			// Send Confirmation Email
			try {
				$post->notify(new PaymentSent($payment, $post));
			} catch (\Exception $e) {
				if (isFromApi()) {
					self::$errors[] = $e->getMessage();
					return self::error(400);
				} else {
					flash($e->getMessage())->error();
				}
			}
			
			// Send to Admin the Payment Notification Email
			try {
				if ($admins->count() > 0) {
					Notification::send($admins, new PaymentNotification($payment, $post));
					/*
                    foreach ($admins as $admin) {
						Notification::route('mail', $admin->email)->notify(new PaymentNotification($payment, $post));
                    }
					*/
				}
			} catch (\Exception $e) {
				if (isFromApi()) {
					self::$errors[] = $e->getMessage();
					return self::error(400);
				} else {
					flash($e->getMessage())->error();
				}
			}
		}
		
		return $payment;
	}
	
	/**
	 * @return array
	 */
	public static function getOptions()
	{
		$options = [];
		
		$paymentMethod = PaymentMethod::active()->where('name', 'astropay')->first();
		if (!empty($paymentMethod)) {
			$options[] = (object)[
				'name'     => mb_ucfirst(trans('admin.settings')),
				'url'      => admin_url('payment_methods/' . $paymentMethod->id . '/edit'),
				'btnClass' => 'btn-info',
			];
		}
		
		return $options;
	}
	
	/**
	 * @return bool
	 */
	public static function installed()
	{
		$paymentMethod = PaymentMethod::active()->where('name', 'astropay')->first();
		if (empty($paymentMethod)) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * @return bool
	 */
	public static function install()
	{
		// Remove the plugin entry
		self::uninstall();
		
		// Plugin data
		$data = [
			'id'                => 5,
			'name'              => 'astropay',
			'display_name'      => 'AstroPay',
			'description'       => null,
			'has_ccbox'         => 0,
			'is_compatible_api' => 1,
			'lft'               => 5,
			'rgt'               => 5,
			'depth'             => 1,
			'active'            => 1,
		];
		
		try {
			// Create plugin data
			$paymentMethod = PaymentMethod::create($data);
			if (empty($paymentMethod)) {
				return false;
			}
		} catch (\Exception $e) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * @return bool
	 */
	public static function uninstall()
	{
		$paymentMethod = PaymentMethod::where('name', 'astropay')->first();
		if (!empty($paymentMethod)) {
			$deleted = $paymentMethod->delete();
			if ($deleted > 0) {
				return true;
			}
		}
		
		return false;
	}
}
