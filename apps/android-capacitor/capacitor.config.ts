import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.brightronix.electroplan.android',
  appName: 'ElectroPlan Android',
  webDir: 'www',
  bundledWebRuntime: false,
  server: {
    cleartext: false,
    androidScheme: 'https'
  }
};

export default config;
