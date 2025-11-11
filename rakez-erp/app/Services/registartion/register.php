<?php

namespace App\Services\registartion;

use App\Models\User;
use App\Models\Driver;
use App\Models\Provider_Product; // Import your ProviderProduct model
use App\Models\Provider_Service; // Import your ProviderService model
use App\Models\FoodType_ProductProvider; // Import your ProviderService model

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache; // Import Cache facade
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class register
{
    /**
     * Register a new user and create related records based on user type.
     *
     * @param array $data
     * @return User
     */
public function register(array $data): User
{
    DB::beginTransaction();

    try {
        // تحقق من وجود البريد الإلكتروني أو رقم الهاتف
        if (isset($data['email'])) {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ];

            if (isset($data['phone'])) {
                $userData['phone'] = $data['phone'];
            }
        } elseif (isset($data['phone'])) {
            $userData = [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
            ];
        } else {
            throw new \Exception('يجب أن تحتوي البيانات إما على البريد الإلكتروني أو رقم الهاتف.');
        }

        // إضافة lat و lang إذا كانت موجودة في البيانات
        if (isset($data['lat'])) {
            $userData['lat'] = $data['lat'];
        }

        if (isset($data['lang'])) {
            $userData['lang'] = $data['lang'];
        }

        // إضافة الرقم الوطني إذا كان النوع 1 أو 2 أو 3 أو 4
        if (in_array($data['type'], [1, 2, 3, 4])) {
            $userData['national_id'] = $data['national_id'];
        }

        // إضافة نوع المستخدم كنص بدلاً من رقم
        $typeNames = [
            0 => 'user',
            1 => 'product_provider',
            2 => 'service_provider',
            3 => 'driver',
            4 => 'food_provider'
        ];

        if (!isset($data['type']) || !array_key_exists($data['type'], $typeNames)) {
            throw new \InvalidArgumentException('نوع المستخدم غير صالح');
        }

        $userData['type'] = $typeNames[$data['type']];

        // تخزين الصورة إذا كانت موجودة للنوع 1 أو 2 أو 3 أو 4
        if (in_array($data['type'], [1, 2, 3, 4]) && isset($data['image'])) {
            $imageName = Str::random(32) . '.' . $data['image']->getClientOriginalExtension();
            $imagePath = 'users/' . $imageName;
            Storage::disk('public')->put($imagePath, file_get_contents($data['image']));
            $userData['image_path'] = $imagePath ? asset('api/storage/' . $imagePath) : null;
        }

        $user = User::create($userData);

        // إنشاء السجلات الإضافية حسب النوع
        switch ($data['type']) {
            case 1:
                Provider_Product::create(['user_id' => $user->id]);
                break;
            case 2:
                Provider_Service::create(['user_id' => $user->id]);
                break;
            case 3:
                Driver::create(['user_id' => $user->id]);
                break;
            case 4:
                // إنشاء سجل مزود المنتجات (الطعام)
                $providerProduct = Provider_Product::create(['user_id' => $user->id]);

                // ربط أنواع الطعام المختارة بالمزود
                if (isset($data['food_type_ids']) && is_array($data['food_type_ids'])) {
                    foreach ($data['food_type_ids'] as $foodTypeId) {
                        FoodType_ProductProvider::create([
                            'food_type_id' => $foodTypeId,
                            'provider__product_id' => $providerProduct->id
                        ]);
                    }
                }
                break;
        }

        DB::commit();

        return $user;

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}

    public function verifyOtp(string $otp, User $user): bool
    {
        // Retrieve the OTP data from the cache using the authenticated user's ID
        $otpData = Cache::get('otp_' . $user->id);

        // Check if the OTP data exists in the cache
        if (!$otpData) {
            throw new \Exception('No OTP data found in cache.');
        }

        // Retrieve the OTP from the cache data
        $sessionOtp = $otpData['otp'];

        // Check if the OTP matches
        if ($otp !== $sessionOtp) {
            throw new \Exception('Invalid OTP.');
        }

        // If OTP is valid, update the user's otp_verified column
        $user->otp = 1; // Assuming the column name is otp_verified
        $user->save(); // Save the changes to the database

        // Clear the OTP data from the cache after successful verification
        Cache::forget('otp_' . $user->id);

        return true; // Return true if OTP verification is successful
    }



    public function generateRandomPassword($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomPassword = '';
        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomPassword;
    }
}
