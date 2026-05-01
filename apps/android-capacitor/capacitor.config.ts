import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.brightronix.electroplan.android',
  appName: 'ElectroPlan Android',
  webDir: 'www',
  bundledWebRuntime: false,
  server: {
    androidScheme: 'https',
    allowNavigation: [
      'androidelectro.brightronix.net'
    ]
  }
};

export default config;
