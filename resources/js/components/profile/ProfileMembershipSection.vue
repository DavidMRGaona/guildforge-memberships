<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import HelpLink from '@/components/knowledge/HelpLink.vue';
import { useRoutes } from '@/composables/useRoutes';
import type { MemberSituation } from '../../types/membership';

const props = defineProps<{
    /** Null when the account has no member record (the tab is hidden then). */
    profileMembership: MemberSituation | null;
    /** The tab's badge and visibility, read by the core: declared so they stay off the markup. */
    profileMembershipOverdue?: number | null;
    profileMembershipVisible?: boolean | null;
}>();

const { t, locale } = useI18n();
const routes = useRoutes();

const situation = computed<MemberSituation | null>(() => props.profileMembership);

const CARD_CLASS = 'rounded-lg border border-default bg-surface p-4';
const LABEL_CLASS = 'text-sm text-base-secondary';
const VALUE_CLASS = 'font-medium break-words text-base-primary';
const LINK_CLASS =
    'rounded-sm text-sm font-medium text-primary underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-page';

/** A calendar day of the site, shown as such: read at UTC midnight so no zone moves it. */
function day(value: string): string {
    return new Intl.DateTimeFormat(locale.value, { dateStyle: 'long', timeZone: 'UTC' }).format(
        new Date(`${value}T00:00:00Z`)
    );
}

function money(cents: number, currency: string): string {
    return new Intl.NumberFormat(locale.value, { style: 'currency', currency }).format(cents / 100);
}
</script>

<template>
    <section v-if="situation" class="space-y-6" data-testid="member-situation">
        <div class="flex items-center gap-2">
            <h2 class="text-xl font-semibold text-base-primary">
                {{ t('memberships.profile.title') }}
            </h2>
            <HelpLink guide="memberships.membership" />
        </div>

        <dl :class="[CARD_CLASS, 'grid gap-4 sm:grid-cols-2']">
            <div>
                <dt :class="LABEL_CLASS">{{ t('memberships.profile.number') }}</dt>
                <dd :class="VALUE_CLASS" data-testid="member-number">
                    {{ situation.memberNumber }}
                </dd>
            </div>
            <div>
                <dt :class="LABEL_CLASS">{{ t('memberships.profile.type') }}</dt>
                <dd :class="VALUE_CLASS">
                    {{ t(`memberships.memberType.${situation.memberType}`) }}
                </dd>
            </div>
            <div>
                <dt :class="LABEL_CLASS">{{ t('memberships.profile.status') }}</dt>
                <dd :class="VALUE_CLASS" data-testid="member-status">
                    {{ t(`memberships.memberStatus.${situation.status}`) }}
                </dd>
            </div>
            <div>
                <dt :class="LABEL_CLASS">{{ t('memberships.profile.joined') }}</dt>
                <dd :class="VALUE_CLASS">{{ day(situation.joinedOn) }}</dd>
            </div>
        </dl>

        <div :class="[CARD_CLASS, 'space-y-1']" data-testid="membership">
            <h3 class="font-semibold text-base-primary">
                {{ t('memberships.profile.membership') }}
            </h3>
            <template v-if="situation.membership">
                <p :class="VALUE_CLASS">
                    {{ t(`memberships.membershipStatus.${situation.membership.status}`) }}
                    ·
                    {{ t(`memberships.periodType.${situation.membership.periodType}`) }}
                </p>
                <p :class="LABEL_CLASS">
                    {{
                        t('memberships.profile.period', {
                            start: day(situation.membership.startsOn),
                            end: day(situation.membership.endsOn),
                        })
                    }}
                </p>
            </template>
            <p v-else :class="LABEL_CLASS">{{ t('memberships.profile.noMembership') }}</p>
            <p v-if="situation.permanent" :class="LABEL_CLASS" data-testid="permanent">
                {{ t('memberships.profile.permanent') }}
            </p>
        </div>

        <div :class="[CARD_CLASS, 'space-y-3']" data-testid="fees">
            <h3 class="font-semibold text-base-primary">{{ t('memberships.profile.fees') }}</h3>
            <p v-if="situation.pendingFees.length === 0" :class="LABEL_CLASS">
                {{ t('memberships.profile.upToDate') }}
            </p>
            <ul v-else>
                <li
                    v-for="(fee, index) in situation.pendingFees"
                    :key="`${fee.dueOn}-${index}`"
                    class="flex flex-wrap items-center justify-between gap-2 border-t border-default py-2 first:border-t-0 first:pt-0 last:pb-0"
                >
                    <span :class="VALUE_CLASS">{{ money(fee.amountCents, fee.currency) }}</span>
                    <span class="flex flex-wrap items-center gap-2 text-sm text-base-secondary">
                        <span
                            v-if="fee.overdue"
                            class="inline-block rounded-full bg-error-light px-2 py-0.5 text-xs font-medium text-base-primary"
                            data-testid="overdue"
                        >
                            {{ t('memberships.profile.overdue') }}
                        </span>
                        <span>{{ t('memberships.profile.dueOn', { date: day(fee.dueOn) }) }}</span>
                    </span>
                </li>
            </ul>

            <div class="border-t border-default pt-3">
                <p :class="LABEL_CLASS">{{ t('memberships.profile.lastPayment') }}</p>
                <p v-if="situation.lastPayment" :class="VALUE_CLASS" data-testid="last-payment">
                    {{
                        t('memberships.profile.lastPaymentText', {
                            amount: money(
                                situation.lastPayment.amountCents,
                                situation.lastPayment.currency
                            ),
                            date: day(situation.lastPayment.paidOn),
                        })
                    }}
                </p>
                <p v-else :class="LABEL_CLASS">{{ t('memberships.profile.noPayments') }}</p>
            </div>
        </div>

        <div class="space-y-2">
            <p class="text-sm text-base-secondary">{{ t('memberships.profile.readOnly') }}</p>
            <Link :href="routes.about" :class="LINK_CLASS" data-testid="contact">
                {{ t('memberships.profile.contact') }}
            </Link>
        </div>
    </section>
</template>
