<?php

namespace App\Models;

use Carbon\Carbon;
use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Arr;

class Order extends Model
{
    use HasFactory;

    protected $casts = [
        'order_amount' => 'float',
        'coupon_discount_amount' => 'float',
        'total_tax_amount' => 'float',
        'store_discount_amount' => 'float',
        'delivery_address_id' => 'integer',
        'delivery_man_id' => 'integer',
        'delivery_charge' => 'float',
        'additional_charge' => 'float',
        'user_id' => 'integer',
        'zone_id' => 'integer',
        'scheduled' => 'integer',
        'store_id' => 'integer',
        'details_count' => 'integer',
        'module_id' => 'integer',
        'dm_vehicle_id' => 'integer',
        'processing_time' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'original_delivery_charge' => 'float',
        'receiver_details' => 'array',
        'dm_tips' => 'float',
        'distance' => 'float',
        'tax_percentage' => 'float',
        'prescription_order' => 'boolean',
        'cutlery' => 'boolean'
    ];

    protected $appends = ['module_type'];

    public function setDeliveryChargeAttribute($value)
    {
        $this->attributes['delivery_charge'] = round($value, 3);
    }

    public function getOrderStatusAttribute()
    {
        return $this->orderDetailsData() ? Arr::get($this->orderDetailsData(), 'status') : null;
    }

    public function setOrderStatusAttribute($value)
    {
        $details = $this->orderDetailsData();
        if ($details) {
            $details->status = $value;
            $details->save();
        }
    }

    public function scopeStatusSearch($q, $params)
    {
        $dm = DeliveryMan::where(['auth_token' => request()->get('token')])->first();
        $vendorId = Arr::get(request(), 'vendor.id');
        $store_id = request()->get('store_id', Arr::get($dm, 'store_id'));
        /*
         *  => function ($query) use ($params) {
                    if (is_array($params))
                        $query->whereIn('status', $params);
                    else
                        $query->where('status', $params);
                }
         */
        $q->with(['details'])->whereHas('details', function ($query) use ($params, $vendorId, $store_id) {
            if (is_array($params))
                $query->whereIn('status', $params);
            else
                $query->where('status', $params);

            $query->whereHas('store', function ($inner) use ($vendorId, $store_id) {
                if ($vendorId)
                    $inner->where('vendor_id', $vendorId);
                elseif ($store_id) {
                    if (is_array($store_id))
                        $inner->whereIn('id', $store_id);
                    else
                        $inner->where('id', $store_id);
                }
            });
        });
        return $q;
    }

    public function getOrderDetailsDataAttribute()
    {
        return $this->orderDetailsData();
    }


    private function orderDetailsData()
    {
        $dm = DeliveryMan::where(['auth_token' => request()->get('token')])->first();
        $details = $this->details;
        $vendorId = Arr::get(request(), 'vendor.id');
        $store_id = request()->get('store_id', Arr::get($dm, 'store_id'));
        return collect($details)->first(function ($detail) use ($vendorId, $dm, $store_id) {
            if ($vendorId)
                return Arr::get($detail, 'store.vendor_id') == $vendorId;
            elseif ($dm && $store_id) {
                if (is_array($store_id))
                    return in_array($detail->store_id, $store_id);
                else
                    return $detail->store_id == $store_id;
            } else
                return null;
        });
//        return $this->details()->whereHas('store', function ($query) use ($dm) {
//            if (isset(request()['vendor'])) {
//                $query->where('vendor_id', Arr::get(request(), 'vendor.id'));
//            } elseif (isset($dm)) {
//                if ($store_id = request()->get('store_id', $dm->store_id)) {
//                    if (is_array($store_id))
//                        $query->whereIn('id', $store_id);
//                    else
//                        $query->where('id', $store_id);
//                }
//            }
//        })->first();
    }


    public function details()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function vendorOrderDetails($vendor)
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_code', 'code');
    }

    public function store()
    {
        return $this->hasOneThrough(
            Store::class, // Target model
            OrderDetail::class, // Intermediate model
            'order_id', // Foreign key on OrderDetail model
            'id', // Foreign key on Store model
            'id', // Local key on Order model
            'store_id' // Local key on OrderDetail model
        );
    }

//        return $this->belongsTo(Store::class, 'store_id');

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function delivery_history()
    {
        return $this->hasMany(DeliveryHistory::class, 'order_id');
    }

    public function dm_last_location()
    {
        // return $this->hasOne(DeliveryHistory::class, 'order_id')->latest();
        return $this->delivery_man->last_location();
    }

    public function transaction()
    {
        return $this->hasOne(OrderTransaction::class);
    }

    public function parcel_category()
    {
        return $this->belongsTo(ParcelCategory::class);
    }

    public function refund()
    {
        return $this->hasOne(Refund::class, 'order_id');
    }

    public function getModuleTypeAttribute()
    {
        return $this->module ? $this->module->module_type : null;
    }

    public function scopeAccepteByDeliveryman($query)
    {
        return $query->statusSearch('accepted');
    }

    public function scopePreparing($query)
    {
        return $query->statusSearch(['confirmed', 'processing', 'handover']);
    }

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }

    public function scopeOngoing($query)
    {
        return $query->statusSearch(['accepted', 'confirmed', 'processing', 'handover', 'picked_up']);
    }

    public function scopeItemOnTheWay($query)
    {
        return $query->statusSearch('picked_up');
    }

    public function scopePending($query)
    {
        return $query->statusSearch('pending');
    }

    public function scopeFailed($query)
    {
        return $query->statusSearch('failed');
    }

    public function scopeCanceled($query)
    {
        return $query->statusSearch('canceled');
    }

    public function scopeDelivered($query)
    {
        return $query->statusSearch('delivered');
    }

    public function scopeNotRefunded($query)
    {
        return $query->where(function ($query) {
            $query->whereNotIn('order_status', ['refunded']);
        });
    }

    public function scopeRefunded($query)
    {
        return $query->statusSearch('refunded');
    }

    public function scopeRefund_requested($query)
    {
        return $query->statusSearch('refund_requested');
    }

    public function scopeRefund_request_canceled($query)
    {
        return $query->statusSearch('refund_request_canceled');
    }

    public function scopeSearchingForDeliveryman($query)
    {
        return $query->whereNull('delivery_man_id')->whereIn('order_type', ['delivery', 'parcel'])->whereNotIn('order_status', ['delivered', 'failed', 'canceled', 'refund_requested', 'refund_request_canceled', 'refunded']);
    }

    public function scopeDelivery($query)
    {
        return $query->where('order_type', '=', 'delivery');
    }

    public function scopeScheduled($query)
    {
        return $query->whereRaw('created_at <> schedule_at')->where('scheduled', '1');
    }

    public function scopeOrderScheduledIn($query, $interval)
    {
        return $query->where(function ($query) use ($interval) {
            $query->whereRaw('created_at <> schedule_at')->where(function ($q) use ($interval) {
                $q->whereBetween('schedule_at', [Carbon::now()->toDateTimeString(), Carbon::now()->addMinutes($interval)->toDateTimeString()]);
            })->orWhere('schedule_at', '<', Carbon::now()->toDateTimeString());
        })->orWhereRaw('created_at = schedule_at');
    }


    public function scopeStoreOrder($query)
    {
        return $query->where(function ($q) {
            $q->where('order_type', 'take_away')->orWhere('order_type', 'delivery');
        });
    }

    public function scopeDmOrder($query)
    {
        return $query->where(function ($q) {
            $q->where('order_type', 'parcel')->orWhere('order_type', 'delivery');
        });
    }

    public function scopeParcelOrder($query)
    {
        return $query->where('order_type', 'parcel');
    }

    public function scopePos($query)
    {
        return $query->where('order_type', '=', 'pos');
    }

    public function scopeNotpos($query)
    {
        return $query->where('order_type', '<>', 'pos');
    }

    public function scopeNotDigitalOrder($query)
    {
        return $query->where('payment_method', '!=', 'digital_payment');
    }

    public function getCreatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
    }

    protected static function boot()
    {
        parent::boot();
    }
}
