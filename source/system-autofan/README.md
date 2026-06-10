**Dynamix Auto Fan Control (Enhanced Fork)**

Fork of [unraid/dynamix](https://github.com/unraid/dynamix) by [@mrjacobarussell](https://github.com/mrjacobarussell).

Automatically controls fan speed based on temperature. High and low thresholds ramp the fan up or down. This fork adds hardware sensor probe support and stable USB controller path resolution.

---

**Install**

In Unraid, go to **Plugins** → **Install Plugin** and paste:

```
https://raw.githubusercontent.com/mrjacobarussell/dynamix/corsair-commander-pro/unRAIDv6/dynamix.system.autofan.plg
```

---

**What this fork adds**

**1. Hardware sensor probe temperature source**
Fan speed can be driven by any hwmon temperature sensor (e.g. Corsair Commander Pro case probes, CPU package, motherboard sensors) instead of — or in addition to — hard drive temperatures.

- New **Temperature source** dropdown: `Hard drives (smartctl)` or `Hardware sensor probe`
- When sensor mode is selected, a picker lists all available hwmon temp sensors with live readings
- `autofan` script gains a `-s <sensor_path>` flag for the sensor path

**2. Stable USB hwmon path resolution across reboots**
USB hwmon controllers (Corsair Commander Pro, etc.) get a different `hwmonN` number each boot depending on device init order. The original plugin saves the path at config time — after a reboot the number changes and fan control silently breaks.

- New **Stable device name** field maps to `hwmon_name` in cfg (e.g. `corsaircpro`)
- `rc.autofan` resolves the current `hwmonN` directory by kernel device name at start/stop time
- No udev rules needed — works automatically as long as the device stays in the same USB port

**3. Fan picker filtered by chip**
The **PWM fan** field is now a dropdown populated with fan sensors, filtered to the same hwmon chip as the selected PWM controller. Avoids cross-chip mismatches. Detect button auto-selects the matched fan.

**4. Drive filter mode toggle**
The drive list can be used as an **include** list (monitor only selected drives) or an **exclude** list (skip selected drives). Useful when you only want a subset of drives to influence fan speed.

**5. Fan detect bug fix**
The original detect logic compared fans across all chips — a motherboard fan could win the RPM race when a USB controller PWM was cycled. Fixed to only consider fans on the same hwmon chip as the selected PWM.

---

**Corsair Commander Pro setup**

1. Ensure `corsair_cpro` kernel module loads at boot (add `modprobe corsair_cpro` to your `go` file)
2. Install this plugin
3. Open **Utilities → Fan Auto Control**
4. Select your Corsair PWM controller from the dropdown
5. Fan picker automatically shows only Corsair fans — select the one to monitor
6. Set **Temperature source** → `Hardware sensor probe`, pick a Corsair temp probe
7. Set **Stable device name** → `corsaircpro`
8. Apply — fan control survives reboots regardless of hwmon number changes

---

**Contributing / Upstream**

A pull request with these changes has been submitted to [unraid/dynamix#3](https://github.com/unraid/dynamix/pull/3).
