import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'net.brightronix.electroplan',
  appName: 'ElectroPlan',
  webDir: 'www',
  bundledWebRuntime: false,
  server: {
    cleartext: false,
    iosScheme: 'https'
  }
};

export default config;
