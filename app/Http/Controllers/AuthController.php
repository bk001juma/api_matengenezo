<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    //function to create user
    public function register(Request $request)
    {
        try {
            $request->validate([
                'real_name' => 'required|string',
                'email' => 'required|email|unique:users',
                'phone' => 'required|string|unique:users',
                'gender' => 'required|string|in:male,female,other',
                'password' => 'required|string|min:6',
            ]);

            $generatedUsername = $this->generateUniqueUsername();

            $user = User::create([
                'real_name' => $request->real_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'gender' => $request->gender,
                'role' => 'user',
                'generated_username' => $generatedUsername,
                'password' => Hash::make($request->password),
            ]);

            return response()->json([
                'message' => 'User registered successfully',
                'user' => $user,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Something went wrong during registration.',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    //function for login
    public function login(Request $request)
    {
        try {
            $request->validate([
                'generated_username' => 'required|string',
                'password' => 'required|string',
            ]);

            $user = User::where('generated_username', $request->generated_username)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            Log::error('Login Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Something went wrong during login.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    public function logout(Request $request)
{
    try {
        if ($request->user()) {
            // Revoke all tokens for the user
            $request->user()->tokens()->delete();

            return response()->json([
                'message' => 'Logged out successfully'
            ], 200);
        }

        return response()->json([
            'message' => 'No authenticated user'
        ], 401);
    } catch (\Exception $e) {
        Log::error('Logout Error: ' . $e->getMessage());
        return response()->json([
            'error' => 'Logout failed',
            'details' => $e->getMessage()
        ], 500);
    }
}


    //generate unique user number 
    private function generateUniqueUsername()
    {
        $yearSuffix = date('y');

        do {
            $fourDigits = rand(1000, 9999);
            $candidate = "$fourDigits/MU.$yearSuffix";
        } while (User::where('generated_username', $candidate)->exists());

        return $candidate;
    }
}
